<?php

namespace Tests\Feature;

use App\Mail\TakeCareOfPlant;
use App\Models\Plant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class PlantCareTest extends TestCase
{
    use RefreshDatabase;

    public function test_schedule_marks_due_plants_and_emails_their_owner(): void
    {
        $this->travelTo(now()->startOfDay());
        Mail::fake();

        $owner = User::factory()->create();
        $due = $this->createPlant($owner, 10);
        $upcoming = $this->createPlant($owner, 1);

        $this->artisan('checkForWatering')->assertSuccessful();
        $this->artisan('checkForFertilizing')->assertSuccessful();
        $this->assertDatabaseHas('needs', [
            'plant_id' => $due->id,
            'need_watering' => true,
            'need_fertilizing' => true,
        ]);
        $this->assertDatabaseHas('needs', [
            'plant_id' => $upcoming->id,
            'need_watering' => false,
            'need_fertilizing' => false,
        ]);

        $this->artisan('email:care')->assertSuccessful();
        Mail::assertSentCount(1);
        Mail::assertSent(TakeCareOfPlant::class, fn ($mail) => $mail->hasTo($owner->email) && $mail->plant->is($due));

        (new TakeCareOfPlant($due))->assertSeeInHtml($due->name);
    }

    private function createPlant(User $owner, int $daysSinceCare): Plant
    {
        $plant = $owner->plants()->create(['name' => 'Test fern']);
        $plant->history()->create([
            'watered_at' => now()->subDays($daysSinceCare),
            'fertilized_at' => now()->subDays($daysSinceCare),
        ]);
        $plant->needs()->create(['watering_frequency' => 3, 'fertilizing_frequency' => 10]);

        return $plant;
    }
}
