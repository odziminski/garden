<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class GardenIntegrityTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_plant_cannot_have_two_care_settings_rows(): void
    {
        $plant = User::factory()->create()->plants()->create(['name' => 'My fern']);
        $plant->needs()->create(['watering_frequency' => 7, 'fertilizing_frequency' => 30]);
        $this->expectException(QueryException::class);
        $plant->needs()->create(['watering_frequency' => 8, 'fertilizing_frequency' => 30]);
    }

    public function test_legacy_care_values_survive_removal_of_the_original_plant_columns(): void
    {
        require_once database_path('migrations/2022_05_09_182112_remove_plants_columns.php');
        $migration = new \RemovePlantsColumns;
        $migration->down();
        $user = User::factory()->create();
        $id = DB::table('plants')->insertGetId([
            'name' => 'Legacy fern', 'user_id' => $user->id,
            'watering_frequency' => 12, 'fertilizing_frequency' => 42,
            'need_watering' => true, 'watered_at' => '2022-04-01 12:30:00',
        ]);

        $migration->up();

        $this->assertDatabaseHas('needs', ['plant_id' => $id, 'watering_frequency' => 12, 'fertilizing_frequency' => 42, 'need_watering' => true]);
        $this->assertDatabaseHas('history', ['plant_id' => $id, 'watered_at' => '2022-04-01 12:30:00']);
    }
}
