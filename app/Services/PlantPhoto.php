<?php

namespace App\Services;

use App\Http\Requests\StorePlantRequest;
use App\Models\Plant;
use App\Models\PlantPhotoEntry;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class PlantPhoto
{
    public function __construct(
        private readonly PlantJournalPhotos $storage,
        private readonly PlantPhotoCleanup $cleanup,
        private readonly PlantIdentification $identification,
    ) {
    }

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
        if (!$request->user()->hasVerifiedEmail()) {
            throw ValidationException::withMessages([$field => 'Verify your email before uploading plant photos.']);
        }

        $publicId = 'plant_covers/'.Str::uuid();
        try {
            $provider = $this->storage->writeProvider();
            $format = $this->storage->upload($webcam ?: $file, $publicId, provider: $provider);
        } catch (PlantPhotoStorageException $exception) {
            throw ValidationException::withMessages([$field => $exception->getMessage()]);
        }

        return [
            'public_id' => $publicId,
            'format' => $format,
            'provider' => $provider,
        ];
    }

    public function attachCover(Plant $plant, string $publicId, string $format, bool $identify = true, string $provider = 'cloudinary'): void
    {
        $plant->forceFill([
            'avatar' => 'plant.png',
            'cover_public_id' => $publicId,
            'cover_format' => $format,
            'cover_provider' => $provider,
        ])->save();
        $this->cleanup->keepUpload($publicId);

        if ($identify) {
            $this->identification->schedule($plant);
        }
    }

    public function discardCover(Plant $plant): void
    {
        if ($plant->cover_public_id) {
            $this->cleanup->enqueue($plant->cover_public_id, provider: $plant->cover_provider ?? 'cloudinary');

            return;
        }

        $legacy = $this->legacyCover($plant->avatar);
        if ($legacy !== null) {
            $this->cleanup->enqueue($legacy['public_id'], $plant->avatar);
        }
    }

    public function discardPlantMedia(Plant $plant): void
    {
        $this->discardCover($plant);
        foreach (PlantPhotoEntry::query()->forPlant($plant)->get(['public_id', 'provider']) as $entry) {
            $this->cleanup->enqueue($entry->public_id, provider: $entry->provider);
        }
    }

    public function legacyCover(?string $url): ?array
    {
        if ($url === null || !preg_match('~\Ahttps://res\.cloudinary\.com/([A-Za-z0-9_-]+)/image/upload/(?:v[0-9]+/)?(user_uploads/[A-Za-z0-9_-]+)\.(?:jpe?g|png|webp)\z~', $url, $matches)) {
            return null;
        }

        return ['cloud' => $matches[1], 'public_id' => $matches[2]];
    }
}
