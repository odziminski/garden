<?php

namespace Tests\Feature;

use App\Models\GardenMembership;
use App\Models\Plant;
use App\Models\PlantPhotoEntry;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class LocalPlantPhotoTest extends TestCase
{
    use RefreshDatabase;

    public function test_local_journal_photo_is_private_and_deleted_by_the_cleanup_worker(): void
    {
        config(['filesystems.plant_photo_driver' => 'local', 'cloudinary.cloud_url' => null]);
        $disk = Storage::fake('plant-photos');
        $plant = Plant::factory()->create();
        $member = User::factory()->create();
        GardenMembership::query()->create(['owner_id' => $plant->user_id, 'member_id' => $member->id]);

        $this->actingAs($plant->owner)->post(route('plants.photo-journal.store', $plant), [
            'photo' => UploadedFile::fake()->image('fern.png'),
            'taken_on' => '2026-11-20',
        ])->assertSessionHasNoErrors();
        $entry = PlantPhotoEntry::query()->sole();
        $url = route('plants.photo-journal.show', [$plant, $entry]);

        $this->assertSame('local', $entry->provider);
        $disk->assertExists($entry->public_id);
        $this->actingAs($member)->get($url)->assertNotFound();
        $this->actingAs($plant->owner)->get($url)->assertOk()
            ->assertHeader('Content-Type', 'image/png')->assertHeader('X-Content-Type-Options', 'nosniff');
        $this->delete($url)->assertRedirect();
        $this->artisan('photos:cleanup')->assertSuccessful();
        $disk->assertMissing($entry->public_id);
    }

    public function test_local_webcam_cover_and_existing_cloudinary_photo_use_their_recorded_providers(): void
    {
        config([
            'filesystems.plant_photo_driver' => 'local',
            'cloudinary.cloud_url' => 'cloudinary://test-key:test-secret@demo',
            'services.plant_id.key' => null,
        ]);
        $disk = Storage::fake('plant-photos');
        $owner = User::factory()->create();
        $bytes = file_get_contents(public_path('images/plant.png'));

        $this->actingAs($owner)->post(route('add-plant-query'), [
            'name' => 'Local fern',
            'watering_frequency' => 7,
            'webcamAvatar' => 'data:image/png;base64,'.base64_encode($bytes),
        ])->assertSessionHasNoErrors();
        $plant = $owner->plants()->sole();
        $this->assertSame('local', $plant->cover_provider);
        $disk->assertExists($plant->cover_public_id);

        config(['filesystems.plant_photo_driver' => 'cloudinary']);
        $response = $this->get(route('plants.cover', $plant))->assertOk()->assertHeader('Content-Type', 'image/png');
        $this->assertStringContainsString('no-store', $response->headers->get('Cache-Control'));
        $this->assertSame($bytes, $response->streamedContent());
        $this->actingAs(User::factory()->create())->get(route('plants.cover', $plant))->assertNotFound();

        config(['filesystems.plant_photo_driver' => 'local']);
        $cloudJournal = PlantPhotoEntry::query()->create([
            'plant_id' => $plant->id,
            'taken_on' => '2026-11-20',
            'public_id' => 'plant_journal/cloudinary-fern',
            'format' => 'png',
        ]);
        $this->actingAs($owner)->get(route('plants.photo-journal.show', [$plant, $cloudJournal]))->assertRedirect();
        $this->delete(route('deletePlant', $plant))->assertRedirect();
        $this->assertDatabaseHas('plant_photo_cleanup', ['public_id' => $plant->cover_public_id, 'provider' => 'local']);
        $this->assertDatabaseHas('plant_photo_cleanup', ['public_id' => $cloudJournal->public_id, 'provider' => 'cloudinary']);
    }

    public function test_photo_comparison_only_shows_entries_from_this_plants_private_journal(): void
    {
        $plant = Plant::factory()->create();
        $createPhoto = fn (Plant $ownerPlant, string $name, string $date) => PlantPhotoEntry::query()->create([
            'plant_id' => $ownerPlant->id,
            'taken_on' => $date,
            'public_id' => 'plant_journal/'.$name,
            'format' => 'png',
            'note' => $name,
        ]);
        $early = $createPhoto($plant, 'Early photo', '2026-11-01');
        $late = $createPhoto($plant, 'Late photo', '2026-11-19');
        $other = $createPhoto(Plant::factory()->create(), 'Other private photo', '2026-11-11');
        $url = route('plants', [$plant, 'compare_first' => $late->id, 'compare_second' => $early->id]);
        $this->actingAs($plant->owner)->get($url)
            ->assertSeeInOrder(['Compare journal photos', 'Early photo', 'Late photo']);
        $this->get(route('plants', [$plant, 'compare_first' => $late->id, 'compare_second' => $other->id]))
            ->assertSee('Choose another photo to compare.')->assertDontSee('Other private photo');

        $member = User::factory()->create();
        GardenMembership::query()->create(['owner_id' => $plant->user_id, 'member_id' => $member->id]);
        $this->actingAs($member)->get($url)->assertOk()->assertDontSee('Compare journal photos')->assertDontSee('Early photo');
    }
}
