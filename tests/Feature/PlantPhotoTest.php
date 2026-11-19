<?php

namespace Tests\Feature;

use App\Models\User;
use Cloudinary\Cloudinary;
use GuzzleHttp\Client;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\Psr7\Response;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class PlantPhotoTest extends TestCase
{
    use RefreshDatabase;

    public function test_webcam_photo_and_partial_identification_are_saved(): void
    {
        config([
            'cloudinary.cloud_url' => 'cloudinary://test-key:test-secret@demo',
            'services.plant_id.key' => 'test-key',
        ]);
        $url = 'https://res.cloudinary.com/demo/image/upload/user_uploads/fern.png';
        Cloudinary::setDefaultHttpClient(new Client([
            'handler' => new MockHandler([
                new Response(200, ['Content-Type' => 'application/json'], json_encode(['secure_url' => $url])),
            ]),
        ]));

        Http::preventStrayRequests();
        Http::fake([
            'https://api.plant.id/v2/identify' => Http::response([
                'suggestions' => [['plant_name' => 'Nephrolepis exaltata']],
            ]),
        ]);

        $owner = User::factory()->create();
        $webcam = 'data:image/png;base64,'.base64_encode(file_get_contents(public_path('images/plant.png')));

        try {
            $this->actingAs($owner)->post(route('add-plant-query'), [
                'name' => 'Fern',
                'watering_frequency' => 7,
                'fertilizing_frequency' => 30,
                'webcamAvatar' => $webcam,
            ])->assertRedirect(route('browse'));
        } finally {
            Cloudinary::setDefaultHttpClient(null);
        }

        $plant = $owner->plants()->sole();
        $this->assertSame($url, $plant->avatar);
        $this->assertSame('Nephrolepis exaltata', $plant->plantData->plant_name);
        $this->assertSame('', $plant->plantData->common_name);
        Http::assertSent(fn (Request $request) => $request['images'] === [$webcam]);
    }
}
