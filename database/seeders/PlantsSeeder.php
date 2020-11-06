<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Carbon\Carbon;

class PlantsSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        DB::table('plants')->insert([
            'user_id' => 1,
            'name' => Str::random(10),
            'created_at' => Carbon::now()->subDays(rand(1, 55)),
            'watered_at' => Carbon::now()->subDays(rand(1, 55)),
            'watering_frequency_week' => rand(1,7),
            'fertilized_at' => Carbon::now()->subDays(rand(1, 55)),
            'fertilizing_frequency_year' => rand(1,12)
            
        ]);
    }
}
