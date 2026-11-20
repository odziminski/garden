<?php

namespace App\Services;

use Cloudinary\Api\Exception\ApiError;
use Cloudinary\Api\Upload\UploadApi;
use Cloudinary\Cloudinary;
use DateTimeInterface;
use Illuminate\Contracts\Config\Repository;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Log;
use InvalidArgumentException;
use Psr\Http\Client\ClientExceptionInterface;

class PlantJournalPhotos
{
    private const DELIVERY_TYPE = 'authenticated';

    public function __construct(
        private readonly Repository $config,
        private readonly PlantPhotoCleanup $cleanup,
    ) {
    }

    public function upload(UploadedFile|string $photo, string $publicId, ?string $expectedCloud = null): string
    {
        $api = $this->uploadApi($expectedCloud);
        $this->cleanup->reserveUpload($publicId);

        try {
            $response = $api->upload($photo instanceof UploadedFile ? $photo->getPathname() : $photo, [
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
            Log::warning('Plant photo upload failed.', ['public_id' => $publicId, 'error_type' => $exception::class]);
        }

        throw new PlantPhotoStorageException('The photo could not be uploaded. Please try again.');
    }

    public function downloadUrl(string $publicId, string $format, DateTimeInterface $issuedAt, int $ttl = 300): string
    {
        return $this->uploadApi()->privateDownloadUrl($publicId, $format, [
            'type' => self::DELIVERY_TYPE,
            'resource_type' => 'image',
            'attachment' => false,
            'expires_at' => $issuedAt->getTimestamp() + $ttl,
        ]);
    }

    public function delete(string $publicId, string $deliveryType = self::DELIVERY_TYPE, ?string $expectedCloud = null): void
    {
        try {
            $response = $this->uploadApi($expectedCloud)->destroy($publicId, [
                'type' => $deliveryType,
                'resource_type' => 'image',
                'invalidate' => true,
            ]);

            if (in_array($response['result'] ?? null, ['ok', 'not found'], true)) {
                return;
            }
        } catch (ApiError|ClientExceptionInterface $exception) {
            Log::warning('Plant photo deletion failed.', ['public_id' => $publicId, 'error_type' => $exception::class]);
        }

        throw new PlantPhotoStorageException('The photo could not be removed. Please try again.');
    }

    private function uploadApi(?string $expectedCloud = null): UploadApi
    {
        $url = $this->config->get('cloudinary.cloud_url');

        if (is_string($url) && $url !== '') {
            try {
                $cloudinary = new Cloudinary($url);
                $cloudinary->configuration->cloud->assertNotEmpty(['cloudName', 'apiKey', 'apiSecret']);
                if ($expectedCloud !== null && $expectedCloud !== $cloudinary->configuration->cloud->cloudName) {
                    throw new PlantPhotoStorageException('Cloudinary configuration does not match the original photo.');
                }
                $cloudinary->configuration->api->connectionTimeout = 5;
                $cloudinary->configuration->api->timeout = 20;
                $cloudinary->configuration->api->uploadTimeout = 20;

                return $cloudinary->uploadApi();
            } catch (InvalidArgumentException) {
                Log::warning('Plant photo storage is configured incorrectly.');
            }
        }

        throw new PlantPhotoStorageException('Photo storage is currently unavailable.');
    }
}
