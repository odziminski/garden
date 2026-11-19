<?php

namespace Tests\Unit;

use App\Services\PlantJournalPhotos;
use App\Services\PlantPhotoStorageException;
use DateTimeImmutable;
use Illuminate\Config\Repository;
use PHPUnit\Framework\TestCase;

class PlantJournalPhotosTest extends TestCase
{
    public function test_delivery_url_signs_an_authenticated_image_for_five_minutes(): void
    {
        $photos = new PlantJournalPhotos(new Repository([
            'cloudinary' => ['cloud_url' => 'cloudinary://test-key:test-secret@demo'],
        ]));

        $url = $photos->downloadUrl('plant_journal/example', 'webp', new DateTimeImmutable('2026-11-20T00:01:00Z'));

        $this->assertStringStartsWith('https://api.cloudinary.com/v1_1/demo/image/download?', $url);
        parse_str(parse_url($url, PHP_URL_QUERY), $query);
        $this->assertSame('plant_journal/example', $query['public_id']);
        $this->assertSame('webp', $query['format']);
        $this->assertSame('authenticated', $query['type']);
        $this->assertSame('1795133160', $query['expires_at']);
        $this->assertNotEmpty($query['signature']);
        $this->assertStringNotContainsString('test-secret', $url);
    }

    public function test_missing_configuration_cannot_generate_a_download_url(): void
    {
        $photos = new PlantJournalPhotos(new Repository());

        $this->expectException(PlantPhotoStorageException::class);
        $photos->downloadUrl('plant_journal/example', 'webp', new DateTimeImmutable('2026-11-20T00:01:00Z'));
    }
}
