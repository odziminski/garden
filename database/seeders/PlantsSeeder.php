<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Support\Arr;
use App\Models\Plant;

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
         
        return $random = Arr::random($samplePlants);
    }

    public function run()
    {
        Plant::create([
            'user_id' => 1,
            'avatar' => "http://127.0.0.1:8000/images/plant.png",
            'name' => self::getRandomPlantName(),
            'species' => self::getRandomPlantName(),
            'created_at' => Carbon::now()->subDays(rand(1, 55)),
            'watered_at' => Carbon::now()->subDays(rand(1, 55)),
            'watering_frequency' => rand(1, 15),
            'need_watering' =>rand(0, 1),
            'fertilized_at' => Carbon::now()->subDays(rand(1, 55)),
            'fertilizing_frequency' => rand(1, 15),
            'need_fertilizing' =>rand(0, 1),
        ]);
    }
}
