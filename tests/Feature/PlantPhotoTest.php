<?php

namespace Tests\Feature;

use App\Models\Plant;
use App\Models\GardenMembership;
use App\Models\User;
use Cloudinary\Cloudinary;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\ConnectException;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\Psr7\Request as GuzzleRequest;
use GuzzleHttp\Psr7\Response;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Psr\Http\Message\RequestInterface;
use Tests\TestCase;

class PlantPhotoTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        Cloudinary::setDefaultHttpClient(null);
        parent::tearDown();
    }

    public function test_webcam_photo_and_partial_identification_are_saved(): void
    {
        config([
            'cloudinary.cloud_url' => 'cloudinary://test-key:test-secret@demo',
            'services.plant_id.key' => 'test-key',
        ]);
        $this->fakeCloudinary([$this->privateUploadResponse(...)]);
        $bytes = file_get_contents(public_path('images/plant.png'));
        Http::preventStrayRequests();
        Http::fake([
            'https://api.cloudinary.com/*' => Http::response($bytes),
            'https://api.plant.id/v3/identification*' => Http::response([
                'result' => [
                    'is_plant' => ['binary' => true],
                    'classification' => ['suggestions' => [['name' => 'Nephrolepis exaltata']]],
                ],
            ]),
        ]);

        $owner = User::factory()->create();
        $webcam = $this->webcamImage();

        $this->actingAs($owner)->post(route('add-plant-query'), $this->plantInput() + ['webcamAvatar' => $webcam])
            ->assertRedirect(route('browse'));

        $plant = $owner->plants()->sole();
        Http::assertNothingSent();
        $this->get(route('plants', $plant))->assertSee('Plant identification is pending.');
        $this->artisan('photos:identify')->assertSuccessful();
        $plant->refresh();
        $this->assertStringStartsWith('plant_covers/', $plant->cover_public_id);
        $cover = $this->get("/plants/{$plant->id}/cover")->assertRedirect();
        parse_str(parse_url($cover->headers->get('Location'), PHP_URL_QUERY), $query);
        $this->assertSame('authenticated', $query['type']);
        $this->assertLessThanOrEqual(now()->addMinute()->timestamp, (int) $query['expires_at']);
        $this->assertStringContainsString('no-store', $cover->headers->get('Cache-Control'));
        $this->actingAs(User::factory()->create())->get("/plants/{$plant->id}/cover")->assertNotFound();
        $this->assertSame('Nephrolepis exaltata', $plant->plantData->plant_name);
        $this->assertSame('', $plant->plantData->common_name);
        Http::assertSent(fn (Request $request) => $request->hasHeader('Api-Key', 'test-key')
            && str_starts_with($request->url(), 'https://api.plant.id/v3/identification?')
            && $request['images'] === [base64_encode($bytes)]);
    }

    public function test_a_shared_viewer_loses_cover_access_when_membership_ends(): void
    {
        config(['cloudinary.cloud_url' => 'cloudinary://test-key:test-secret@demo']);
        $plant = Plant::factory()->create()->forceFill([
            'cover_public_id' => 'plant_covers/fern',
            'cover_format' => 'png',
        ]);
        $plant->save();
        $member = User::factory()->create();
        $membership = GardenMembership::query()->create(['owner_id' => $plant->user_id, 'member_id' => $member->id]);
        $url = route('plants.cover', $plant);

        $this->get($url)->assertRedirect(route('login'));
        $this->actingAs($member)->get($url)->assertRedirect();
        $membership->delete();
        $this->get($url)->assertNotFound();
    }

    public function test_replacing_a_cover_keeps_identification_when_the_provider_fails(): void
    {
        config(['services.plant_id.key' => 'test-key']);
        $this->fakeCloudinary([$this->privateUploadResponse(...)]);
        Http::preventStrayRequests();
        Http::fake([
            'https://api.cloudinary.com/*' => fn () => Http::response(file_get_contents(public_path('images/plant.png'))),
            'https://api.plant.id/v3/identification*' => Http::response([], 503),
        ]);

        $plant = Plant::factory()->create()->forceFill([
            'cover_public_id' => 'plant_covers/original',
            'cover_format' => 'png',
        ]);
        $plant->save();
        $plant->plantData()->create($this->identification());

        $this->actingAs($plant->owner)->patch(route('editPlant', $plant), $this->plantInput() + ['webcamAvatar' => $this->webcamImage()])
            ->assertRedirect(route('browse'));

        $this->assertSame('Fern', $plant->plantData()->sole()->plant_name);
        $this->assertNotSame('plant_covers/original', $plant->fresh()->cover_public_id);
        $this->assertDatabaseHas('plant_photo_cleanup', ['public_id' => 'plant_covers/original']);
        foreach (range(1, 3) as $attempt) {
            $this->artisan('photos:identify')->assertSuccessful();
            $this->travel(6)->minutes();
        }
        $this->get(route('plants', $plant))->assertSee('Plant identification is unavailable.');
        $this->assertSame('Fern', $plant->plantData()->sole()->plant_name);
        Http::assertSentCount(6);
    }

    public function test_cloudinary_transport_failure_is_reported_as_a_form_error(): void
    {
        $this->fakeCloudinary([new ConnectException('unavailable', new GuzzleRequest('POST', 'https://api.cloudinary.com'))]);
        $owner = User::factory()->create();

        $this->actingAs($owner)->from(route('add-plant'))->post(
            route('add-plant-query'), $this->plantInput() + ['webcamAvatar' => $this->webcamImage()],
        )->assertRedirect(route('add-plant'))->assertSessionHasErrors('webcamAvatar');

        $this->assertDatabaseCount('plants', 0);
        $this->assertDatabaseCount('plant_photo_cleanup', 1);
    }

    public function test_in_flight_identification_cannot_overwrite_the_replacement_cover_result(): void
    {
        config(['services.plant_id.key' => 'test-key']);
        $this->fakeCloudinary([$this->privateUploadResponse(...), $this->privateUploadResponse(...)]);
        $plant = Plant::factory()->create();
        $this->actingAs($plant->owner);
        $input = $this->plantInput() + ['webcamAvatar' => $this->webcamImage()];
        $this->patch(route('editPlant', $plant), $input)->assertRedirect(route('browse'));
        $original = $plant->fresh()->cover_public_id;

        Http::preventStrayRequests();
        Http::fake([
            'https://api.cloudinary.com/*' => fn () => Http::response(file_get_contents(public_path('images/plant.png'))),
            'https://api.plant.id/v3/identification*' => function () use ($plant, $input, $original) {
                if ($plant->fresh()->cover_public_id === $original) {
                    $this->patch(route('editPlant', $plant), $input)->assertRedirect(route('browse'));

                    return Http::response(['result' => ['classification' => ['suggestions' => [['name' => 'Old cover match']]]]]);
                }

                return Http::response(['result' => ['is_plant' => ['binary' => false]]]);
            },
        ]);

        $this->artisan('photos:identify')->assertSuccessful();
        $this->assertNotSame($original, $plant->fresh()->cover_public_id);
        $this->assertDatabaseCount('plant_data', 0);
        $this->get(route('plants', $plant))->assertSee('Plant identification is pending.');

        $this->artisan('photos:identify')->assertSuccessful();
        $this->get(route('plants', $plant))->assertSee('No plant match was found for this cover photo.');
        $this->assertDatabaseCount('plant_data', 0);
    }

    public function test_legacy_cloudinary_cover_can_be_copied_and_the_public_original_removed(): void
    {
        $plant = Plant::factory()->create([
            'avatar' => 'https://res.cloudinary.com/demo/image/upload/v123/user_uploads/fern.png',
        ]);
        $this->fakeCloudinary([
            $this->privateUploadResponse(...),
            function (RequestInterface $request): Response {
                parse_str((string) $request->getBody(), $body);
                $this->assertSame('user_uploads/fern', $body['public_id']);
                $this->assertSame('upload', $body['type']);
                $this->assertSame('1', $body['invalidate']);

                return $this->cloudinaryResponse(['result' => 'ok']);
            },
        ]);

        $this->assertSame(asset('images/plant.png'), $plant->avatarUrl());
        $this->artisan('photos:privatize-covers')->assertSuccessful();
        $this->assertSame(route('plants.cover', $plant, false), $plant->fresh()->avatarUrl());
        $this->assertDatabaseHas('plant_photo_cleanup', ['public_id' => 'user_uploads/fern']);
        $this->artisan('photos:cleanup')->assertSuccessful();
        $this->assertDatabaseCount('plant_photo_cleanup', 0);
    }

    public function test_default_cover_has_no_deployment_hostname_in_storage(): void
    {
        $owner = User::factory()->create();
        $this->actingAs($owner)->post(route('add-plant-query'), $this->plantInput())->assertRedirect(route('browse'));

        $plant = Plant::query()->sole();
        $this->assertSame('plant.png', $plant->avatar);
        $this->assertSame(asset('images/plant.png'), $plant->avatarUrl());
    }

    private function plantInput(): array
    {
        return [
            'name' => 'Fern',
            'watering_frequency' => 7,
            'fertilizing_frequency' => 30,
        ];
    }

    private function identification(): array
    {
        return array_fill_keys([
            'plant_name', 'common_name', 'wikipedia_url', 'wikipedia_description', 'taxonomy_class', 'taxonomy_family',
            'taxonomy_genus', 'taxonomy_kingdom', 'taxonomy_order', 'taxonomy_phylum',
        ], 'Fern');
    }

    private function webcamImage(): string
    {
        return 'data:image/png;base64,'.base64_encode(file_get_contents(public_path('images/plant.png')));
    }

    private function fakeCloudinary(array $responses): void
    {
        config(['cloudinary.cloud_url' => 'cloudinary://test-key:test-secret@demo']);
        Cloudinary::setDefaultHttpClient(new Client(['handler' => new MockHandler($responses)]));
    }

    private function privateUploadResponse(RequestInterface $request): Response
    {
        $body = (string) $request->getBody();
        preg_match('/name="type"[^\r\n]*\r\n(?:[^\r\n]+\r\n)*\r\n([^\r\n]+)/', $body, $type);
        $this->assertSame('authenticated', $type[1] ?? null);
        preg_match('/name="public_id"[^\r\n]*\r\n(?:[^\r\n]+\r\n)*\r\n([^\r\n]+)/', $body, $match);

        return $this->cloudinaryResponse(['public_id' => $match[1], 'format' => 'png']);
    }

    private function cloudinaryResponse(array $body): Response
    {
        return new Response(200, ['Content-Type' => 'application/json'], json_encode($body));
    }
}
