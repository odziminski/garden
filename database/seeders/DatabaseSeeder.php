<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Plant;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     *
     * @return void
     */
    public function run()
    {
        \App\Models\Plant::factory()->count(30)->create(); 


        foreach (Plant::all() as $project){
            $users = App\User::inRandomOrder()->take(rand(1,3))->pluck('id');
            $project->users()->attach($users);
        }
    }
}
