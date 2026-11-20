<?php

namespace App\Services;

use App\Models\Plant;
use GuzzleHttp\Psr7\Utils;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use RuntimeException;

class PlantIdentification
{
    private const MAX_IMAGE_BYTES = 5 * 1024 * 1024;

    public function __construct(private readonly PlantJournalPhotos $storage)
    {
    }

    public function schedule(Plant $plant): void
    {
        if (!config('services.plant_id.key')) {
            DB::table('plant_identifications')->where('plant_id', $plant->id)->delete();

            return;
        }

        DB::table('plant_identifications')->updateOrInsert(['plant_id' => $plant->id], [
            'cover_public_id' => $plant->cover_public_id,
            'status' => 'pending',
            'available_at' => now(),
            'attempts' => 0,
            'claim_token' => null,
        ]);
    }

    public function status(Plant $plant): ?string
    {
        return DB::table('plant_identifications')->where('plant_id', $plant->id)
            ->where('cover_public_id', $plant->cover_public_id)->value('status');
    }

    public function processPending(int $limit): void
    {
        $now = now();
        $jobs = DB::table('plant_identifications')->where('status', 'pending')->where('available_at', '<=', $now)
            ->orderBy('available_at')->orderBy('plant_id')->limit($limit)->get();

        foreach ($jobs as $job) {
            $token = (string) Str::uuid();
            $claimed = DB::table('plant_identifications')
                ->where('plant_id', $job->plant_id)->where('cover_public_id', $job->cover_public_id)
                ->where('status', 'pending')->where('available_at', '<=', $now)->update([
                    'attempts' => DB::raw('attempts + 1'),
                    'available_at' => now()->addMinutes(5),
                    'claim_token' => $token,
                ]);
            if ($claimed !== 1) {
                continue;
            }

            $plant = Plant::query()->whereKey($job->plant_id)->where('cover_public_id', $job->cover_public_id)->first();
            if ($plant === null) {
                continue;
            }

            $result = $job->attempts >= 3 ? null : $this->identify($plant);
            if ($result === null && $job->attempts < 2) {
                continue;
            }

            $this->applyResult($plant, $token, $result);
        }
    }

    private function applyResult(Plant $plant, string $token, ?array $result): void
    {
        DB::transaction(function () use ($plant, $token, $result) {
            $current = Plant::query()->whereKey($plant->id)->lockForUpdate()->first();
            if ($current === null || $current->cover_public_id !== $plant->cover_public_id) {
                return;
            }

            $status = match ($result) {
                null => 'unavailable',
                [] => 'no_match',
                default => 'identified',
            };
            $updated = DB::table('plant_identifications')->where('plant_id', $current->id)
                ->where('claim_token', $token)->update(['status' => $status, 'claim_token' => null]);

            if ($updated === 1 && $result) {
                $current->plantData()->updateOrCreate([], $result);
            }
        }, 3);
    }

    private function identify(Plant $plant): ?array
    {
        $key = config('services.plant_id.key');
        if (!$key) {
            return null;
        }

        try {
            $image = $this->downloadImage($plant);
            if ($image === null) {
                return null;
            }

            $response = Http::connectTimeout(5)->timeout(15)->withoutRedirecting()->withHeaders(['Api-Key' => $key])
                ->withQueryParameters(['details' => 'common_names,url,description,taxonomy'])
                ->post('https://api.plant.id/v3/identification', ['images' => [base64_encode($image)]]);
        } catch (ConnectionException|PlantPhotoStorageException) {
            return null;
        }

        if (!$response->successful()) {
            return null;
        }
        if ($response->json('result.is_plant.binary') === false) {
            return [];
        }

        $suggestions = $response->json('result.classification.suggestions');
        if (!is_array($suggestions)) {
            return null;
        }
        if ($suggestions === []) {
            return [];
        }

        $suggestion = $suggestions[0] ?? null;
        if (!is_array($suggestion) || !is_string($suggestion['name'] ?? null) || trim($suggestion['name']) === '') {
            return null;
        }

        $fields = [
            'plant_name' => 'name',
            'common_name' => 'details.common_names.0',
            'wikipedia_url' => 'details.url',
            'wikipedia_description' => 'details.description.value',
            'taxonomy_class' => 'details.taxonomy.class',
            'taxonomy_family' => 'details.taxonomy.family',
            'taxonomy_genus' => 'details.taxonomy.genus',
            'taxonomy_kingdom' => 'details.taxonomy.kingdom',
            'taxonomy_order' => 'details.taxonomy.order',
            'taxonomy_phylum' => 'details.taxonomy.phylum',
        ];

        return array_map(function (string $path) use ($suggestion): string {
            $value = data_get($suggestion, $path);

            return is_string($value) ? Str::substr($value, 0, 255) : '';
        }, $fields);
    }

    private function downloadImage(Plant $plant): ?string
    {
        if ($plant->cover_provider === 'local') {
            $stream = Utils::streamFor($this->storage->openLocal($plant->cover_public_id));
        } else {
            $url = $this->storage->downloadUrl($plant->cover_public_id, $plant->cover_format, now(), ttl: 60);
            $response = Http::connectTimeout(5)->timeout(10)->withoutRedirecting()
                ->withOptions(['stream' => true, 'read_timeout' => 5])->get($url);
            $stream = $response->toPsrResponse()->getBody();
        }

        try {
            if (isset($response) && (!$response->successful() || (int) $response->header('Content-Length') > self::MAX_IMAGE_BYTES)) {
                return null;
            }

            $image = Utils::copyToString($stream, self::MAX_IMAGE_BYTES + 1);

            return $image !== '' && strlen($image) <= self::MAX_IMAGE_BYTES && $stream->eof() ? $image : null;
        } catch (RuntimeException) {
            return null;
        } finally {
            $stream->close();
        }
    }
}
