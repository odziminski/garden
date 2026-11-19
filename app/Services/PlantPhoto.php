<?php

namespace App\Services;

use App\Http\Requests\StorePlantRequest;
use Cloudinary\Api\Exception\ApiError;
use Cloudinary\Cloudinary;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class PlantPhoto
{
    public static function isValidWebcamImage(string $value): bool
    {
        if (!preg_match('#\Adata:(image/(?:jpeg|png|webp));base64,#', $value, $header)) {
            return false;
        }

        $bytes = base64_decode(substr($value, strlen($header[0])), true);
        if ($bytes === false || strlen($bytes) > 5 * 1024 * 1024) {
            return false;
        }

        $image = @getimagesizefromstring($bytes);

        return $image !== false && $image['mime'] === $header[1];
    }

    public function fromRequest(StorePlantRequest $request): ?array
    {
        $webcam = $request->validated('webcamAvatar');
        $file = $request->file('avatar');
        if (!$webcam && !$file) {
            return null;
        }

        $field = $webcam ? 'webcamAvatar' : 'avatar';
        $cloudUrl = config('cloudinary.cloud_url');
        if (!$cloudUrl) {
            throw ValidationException::withMessages([$field => 'Photo uploads are currently unavailable.']);
        }

        try {
            $cloudinary = new Cloudinary($cloudUrl);
            $image = $cloudinary->uploadApi()->upload($webcam ?: $file->getPathname(), [
                'folder' => 'user_uploads',
                'resource_type' => 'image',
                'timeout' => 20,
            ]);
        } catch (ApiError $exception) {
            report($exception);
            throw ValidationException::withMessages([$field => 'The photo could not be uploaded. Please try again.']);
        }

        return [
            'url' => $image['secure_url'],
            'identification' => $this->identify($webcam ?: base64_encode($file->getContent())),
        ];
    }

    private function identify(string $image): array
    {
        $key = config('services.plant_id.key');
        if (!$key) {
            return [];
        }

        try {
            $response = Http::timeout(10)->withoutRedirecting()->post('https://api.plant.id/v2/identify', [
                'api_key' => $key,
                'images' => [$image],
                'plant_language' => 'en',
                'plant_details' => ['common_names', 'url', 'wiki_description', 'taxonomy'],
            ]);
        } catch (ConnectionException) {
            return [];
        }

        $suggestion = $response->successful() ? $response->json('suggestions.0') : null;
        if (!is_array($suggestion) || empty($suggestion['plant_name'])) {
            return [];
        }

        $fields = [
            'plant_name' => 'plant_name',
            'common_name' => 'plant_details.common_names.0',
            'wikipedia_url' => 'plant_details.url',
            'wikipedia_description' => 'plant_details.wiki_description.value',
            'taxonomy_class' => 'plant_details.taxonomy.class',
            'taxonomy_family' => 'plant_details.taxonomy.family',
            'taxonomy_genus' => 'plant_details.taxonomy.genus',
            'taxonomy_kingdom' => 'plant_details.taxonomy.kingdom',
            'taxonomy_order' => 'plant_details.taxonomy.order',
            'taxonomy_phylum' => 'plant_details.taxonomy.phylum',
        ];

        return array_map(function (string $path) use ($suggestion): string {
            $value = data_get($suggestion, $path);

            return is_string($value) ? Str::substr($value, 0, 255) : '';
        }, $fields);
    }
}
