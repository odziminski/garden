<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PlantAccessTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_can_open_landing_page_but_must_sign_in_to_open_the_garden(): void
    {
        $this->get(route('welcome'))->assertOk();
        $this->get(route('browse'))->assertRedirect(route('login'));
        $this->get(route('add-plant'))->assertRedirect(route('login'));
    }

    public function test_another_user_cannot_manage_a_plant(): void
    {
        $plant = User::factory()->create()->plants()->create(['name' => 'Private fern']);
        $this->actingAs(User::factory()->create());

        $this->get(route('plants', $plant))->assertNotFound();
        $this->get(route('displayEditPlant', $plant))->assertNotFound();
        $this->patch(route('editPlant', $plant))->assertNotFound();
        $this->post(route('updateWatering', $plant))->assertNotFound();
        $this->post(route('updateFertilizing', $plant))->assertNotFound();
        $this->delete(route('deletePlant', $plant))->assertNotFound();

        $this->assertModelExists($plant);
    }

    public function test_plant_form_rejects_invalid_intervals_and_images(): void
    {
        $owner = User::factory()->create();
        $this->actingAs($owner)->postJson(route('add-plant-query'), [
            'name' => 'My fern',
            'watering_frequency' => -1,
            'fertilizing_frequency' => 'tomorrow',
            'webcamAvatar' => 'data:image/png;base64,dGV4dA==',
        ])->assertUnprocessable()->assertJsonValidationErrors([
            'watering_frequency', 'fertilizing_frequency', 'webcamAvatar',
        ]);

        $this->assertDatabaseCount('plants', 0);
    }

    public function test_owner_can_manage_a_plant_through_the_web_routes(): void
    {
        $owner = User::factory()->create();
        $this->actingAs($owner);

        $this->get(route('welcome'))->assertOk();
        $this->get(route('add-plant'))->assertOk();

        $this->post(route('add-plant-query'), [
            'name' => 'My fern',
            'watering_frequency' => 4,
            'fertilizing_frequency' => 30,
        ])->assertRedirect(route('browse'));

        $plant = $owner->plants()->sole();
        $plant->history()->update(['watered_at' => now()->subDays(8), 'fertilized_at' => now()->subDays(36)]);
        $this->artisan('checkForWatering')->assertSuccessful();
        $this->artisan('checkForFertilizing')->assertSuccessful();
        $this->get(route('welcome'))->assertOk()->assertSee('My fern')->assertSee('Next soil check');
        $this->get(route('browse'))->assertOk()->assertSee('My fern');
        $this->get(route('plants', $plant))->assertOk();
        $this->get(route('displayEditPlant', $plant))->assertOk();

        $this->patch(route('editPlant', $plant), [
            'name' => 'My new fern',
            'watering_frequency' => 7,
            'fertilizing_frequency' => 35,
        ])->assertRedirect(route('browse'));

        $this->assertSame('My new fern', $plant->fresh()->name);

        $this->get(route('updateWatering', $plant))->assertMethodNotAllowed();
        $this->get(route('updateFertilizing', $plant))->assertMethodNotAllowed();
        $this->get(route('deletePlant', $plant))->assertMethodNotAllowed();
        $this->get(route('logout'))->assertMethodNotAllowed();
        $this->get('/test')->assertNotFound();

        $this->from(route('plants', $plant))->post(route('updateWatering', $plant))->assertRedirect(route('plants', $plant));
        $this->from(route('browse'))->post(route('updateFertilizing', $plant))->assertRedirect(route('browse'));
        $this->assertDatabaseHas('needs', [
            'plant_id' => $plant->id,
            'need_watering' => false,
            'need_fertilizing' => false,
        ]);

        $this->delete(route('deletePlant', $plant))->assertRedirect(route('browse'));
        $this->assertModelMissing($plant);
        $this->assertDatabaseMissing('needs', ['plant_id' => $plant->id]);
        $this->assertDatabaseMissing('history', ['plant_id' => $plant->id]);
    }
}
