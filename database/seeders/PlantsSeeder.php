<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Support\Arr;

use Carbon\Carbon;

class PlantsSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */

     public function getRandomPlantName(){
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
        $random = Arr::random($samplePlants);
        return $random;
    }

    public function run()
    {
        
        DB::table('plants')->insert([
            'user_id' => 1,
            'name' => self::getRandomPlantName(), 
            'created_at' => Carbon::now()->subDays(rand(1, 55)),
            'watered_at' => Carbon::now()->subDays(rand(1, 55)),
            'watering_frequency' => rand(1,15),
            'fertilized_at' => Carbon::now()->subDays(rand(1, 55)),
            'fertilizing_frequency' => rand(1,15)
        ]);
    }
}
