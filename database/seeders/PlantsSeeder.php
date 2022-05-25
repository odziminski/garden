<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Support\Arr;
use App\Models\Plant;
use App\Models\History;
use App\Models\Needs;

use Carbon\Carbon;

class PlantsSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */

    public function getRandomPlantName()
    {
        $samplePlants = [
            'Aloe',
            'Peace lily',
            'Mother-in-law’s Tongue',
            'Zanzibar Gem',
            'Maidenhair',
            'Rubber Plant',
            'Begonia',
            'Belladonna Lily',
            'Eternal flame',
            'Beach Spider Lily',
            'African Violet',
            'Queens Tears',
            'Madagascar Jasmine',
            'Moth Orchid',
            'Winter Cherry'
        ];
         
        return Arr::random($samplePlants);
    }

    public function run()
    {
       $plant = Plant::create([
            'user_id' => 1,
            'avatar' => "http://127.0.0.1:8000/images/plant.png",
            'name' => self::getRandomPlantName(),
            'species' => self::getRandomPlantName(),
            'created_at' => Carbon::now()->subDays(rand(1, 55)),
        ]);

        History::create([
            'plant_id' => $plant->id,
            'watered_at' => Carbon::today()->subDays(rand(0, 365)),
            'fertilized_at' => Carbon::today()->subDays(rand(0, 365)),
        ]);

        Needs::create([
            'plant_id' => $plant->id,
            'watering_frequency' => rand(1, 15),
            'need_watering' => 0,
            'fertilizing_frequency' => rand(1, 15),
            'need_fertilizing' => 0,
        ]);
    }
}
