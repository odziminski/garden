<?php

namespace App\Services;

use App\Models\Plant;
use App\Models\PlantPhotoEntry;
use Cloudinary\Api\Exception\ApiError;
use Cloudinary\Api\Upload\UploadApi;
use Cloudinary\Cloudinary;
use DateTimeInterface;
use Illuminate\Contracts\Config\Repository;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use InvalidArgumentException;
use Psr\Http\Client\ClientExceptionInterface;
use Throwable;

class PlantJournalPhotos
{
    private const DELIVERY_TYPE = 'authenticated';

    public function __construct(private readonly Repository $config)
    {
    }

    public function upload(UploadedFile $photo, string $publicId): string
    {
        $api = $this->uploadApi();

        try {
            $response = $api->upload($photo->getPathname(), [
                'resource_type' => 'image',
                'type' => self::DELIVERY_TYPE,
                'public_id' => $publicId,
                'overwrite' => false,
            ]);

            $format = $response['format'] ?? null;
            if (($response['public_id'] ?? null) === $publicId && in_array($format, ['jpg', 'jpeg', 'png', 'webp'], true)) {
                return $format;
            }
        } catch (ApiError|ClientExceptionInterface $exception) {
            Log::warning('Journal photo upload failed.', ['public_id' => $publicId, 'error_type' => $exception::class]);
        }

        // A request can fail after Cloudinary has already stored the image.
        $this->discard($publicId);

        throw new PlantPhotoStorageException('The photo could not be uploaded. Please try again.');
    }

    public function downloadUrl(string $publicId, string $format, DateTimeInterface $issuedAt): string
    {
        return $this->uploadApi()->privateDownloadUrl($publicId, $format, [
            'type' => self::DELIVERY_TYPE,
            'resource_type' => 'image',
            'attachment' => false,
            'expires_at' => $issuedAt->getTimestamp() + 300,
        ]);
    }

    public function delete(string $publicId): void
    {
        try {
            $response = $this->uploadApi()->destroy($publicId, [
                'type' => self::DELIVERY_TYPE,
                'resource_type' => 'image',
                'invalidate' => true,
            ]);

            if (in_array($response['result'] ?? null, ['ok', 'not found'], true)) {
                return;
            }
        } catch (ApiError|ClientExceptionInterface $exception) {
            Log::warning('Journal photo deletion failed.', ['public_id' => $publicId, 'error_type' => $exception::class]);
        }

        throw new PlantPhotoStorageException('The photo could not be removed. Please try again.');
    }

    public function discard(string $publicId): void
    {
        try {
            $this->delete($publicId);
        } catch (Throwable $exception) {
            Log::warning('Could not clean up a journal photo.', ['public_id' => $publicId, 'error_type' => $exception::class]);
        }
    }

    public function discardAfterCommit(Plant $plant): void
    {
        $publicIds = PlantPhotoEntry::query()->forPlant($plant)->pluck('public_id');

        if ($publicIds->isNotEmpty()) {
            DB::afterCommit(function () use ($publicIds) {
                foreach ($publicIds as $publicId) {
                    $this->discard($publicId);
                }
            });
        }
    }

    private function uploadApi(): UploadApi
    {
        $url = $this->config->get('cloudinary.cloud_url');

        if (is_string($url) && $url !== '') {
            try {
                $cloudinary = new Cloudinary($url);
                $cloudinary->configuration->cloud->assertNotEmpty(['cloudName', 'apiKey', 'apiSecret']);
                $cloudinary->configuration->api->connectionTimeout = 5;
                $cloudinary->configuration->api->timeout = 20;
                $cloudinary->configuration->api->uploadTimeout = 20;

                return $cloudinary->uploadApi();
            } catch (InvalidArgumentException) {
                Log::warning('Journal photo storage is configured incorrectly.');
            }
        }

        throw new PlantPhotoStorageException('Journal photos are currently unavailable.');
    }
}
