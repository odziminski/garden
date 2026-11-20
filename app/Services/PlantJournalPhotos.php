<?php

namespace App\Services;

use Cloudinary\Api\Exception\ApiError;
use Cloudinary\Api\Upload\UploadApi;
use Cloudinary\Cloudinary;
use DateTimeInterface;
use Illuminate\Contracts\Config\Repository;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use InvalidArgumentException;
use League\Flysystem\FilesystemException;
use Psr\Http\Client\ClientExceptionInterface;
use Symfony\Component\HttpFoundation\File\Exception\FileException;
use Symfony\Component\HttpFoundation\Response;

class PlantJournalPhotos
{
    private const DELIVERY_TYPE = 'authenticated';
    private const MIME_TYPES = [
        'jpeg' => 'image/jpeg',
        'jpg' => 'image/jpeg',
        'png' => 'image/png',
        'webp' => 'image/webp',
    ];

    public function __construct(
        private readonly Repository $config,
        private readonly PlantPhotoCleanup $cleanup,
    ) {
    }

    public function writeProvider(): string
    {
        $provider = $this->config->get('filesystems.plant_photo_driver');
        if (!in_array($provider, ['cloudinary', 'local'], true)) {
            throw new PlantPhotoStorageException('Photo storage is currently unavailable.');
        }

        return $provider;
    }

    public function upload(UploadedFile|string $photo, string $publicId, ?string $expectedCloud = null, string $provider = 'cloudinary'): string
    {
        if ($provider === 'local') {
            return $this->uploadLocal($photo, $publicId);
        }

        $this->ensureCloudinary($provider);
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
            if (($response['public_id'] ?? null) === $publicId && is_string($format) && isset(self::MIME_TYPES[$format])) {
                return $format;
            }
        } catch (ApiError|ClientExceptionInterface $exception) {
            Log::warning('Plant photo upload failed.', ['public_id' => $publicId, 'error_type' => $exception::class]);
        }

        throw new PlantPhotoStorageException('The photo could not be uploaded. Please try again.');
    }

    public function serve(string $publicId, string $format, ?string $provider, int $ttl = 300): Response
    {
        $headers = [
            'Cache-Control' => 'private, no-store',
            'Referrer-Policy' => 'no-referrer',
            'X-Content-Type-Options' => 'nosniff',
        ];
        if ($provider !== 'local') {
            $this->ensureCloudinary($provider ?? 'cloudinary');

            return redirect()->away($this->downloadUrl($publicId, $format, now(), $ttl), 302, $headers);
        }

        $mime = self::MIME_TYPES[$format] ?? null;
        if ($mime === null) {
            throw new PlantPhotoStorageException('The photo could not be opened.');
        }

        $stream = $this->openLocal($publicId);

        return response()->stream(function () use ($stream): void {
            try {
                fpassthru($stream);
            } finally {
                fclose($stream);
            }
        }, 200, $headers + ['Content-Type' => $mime]);
    }

    /** @return resource */
    public function openLocal(string $publicId)
    {
        $this->validateLocalPath($publicId);

        try {
            $stream = Storage::disk('plant-photos')->readStream($publicId);
        } catch (FilesystemException) {
            throw new PlantPhotoStorageException('The photo could not be opened.');
        }

        if (!is_resource($stream)) {
            throw new PlantPhotoStorageException('The photo could not be opened.');
        }

        return $stream;
    }

    private function uploadLocal(UploadedFile|string $photo, string $publicId): string
    {
        $this->validateLocalPath($publicId);
        try {
            $bytes = $photo instanceof UploadedFile
                ? $photo->getContent()
                : base64_decode(explode(',', $photo, 2)[1] ?? '', true);
        } catch (FileException) {
            throw new PlantPhotoStorageException('The photo could not be opened.');
        }

        $image = is_string($bytes) ? @getimagesizefromstring($bytes) : false;
        $format = $image === false ? false : array_search($image['mime'], self::MIME_TYPES, true);
        if ($format === false) {
            throw new PlantPhotoStorageException('The photo could not be opened.');
        }

        $this->cleanup->reserveUpload($publicId, 'local');
        try {
            if (Storage::disk('plant-photos')->put($publicId, $bytes)) {
                return $format;
            }
        } catch (FilesystemException) {
            // The transaction keeps the staged key available for cleanup on any partial write.
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

    public function delete(string $publicId, string $deliveryType = self::DELIVERY_TYPE, ?string $expectedCloud = null, string $provider = 'cloudinary'): void
    {
        if ($provider === 'local') {
            $this->validateLocalPath($publicId);
            try {
                if (Storage::disk('plant-photos')->delete($publicId)) {
                    return;
                }
            } catch (FilesystemException) {
                // The cleanup worker retries failed deletes.
            }

            throw new PlantPhotoStorageException('The photo could not be removed. Please try again.');
        }

        $this->ensureCloudinary($provider);
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

    private function validateLocalPath(string $publicId): void
    {
        if (!preg_match('~\Aplant_(?:covers|journal)/[a-f0-9-]{36}\z~', $publicId)) {
            throw new PlantPhotoStorageException('The photo path is invalid.');
        }
    }

    private function ensureCloudinary(string $provider): void
    {
        if ($provider !== 'cloudinary') {
            throw new PlantPhotoStorageException('Photo storage is currently unavailable.');
        }
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
