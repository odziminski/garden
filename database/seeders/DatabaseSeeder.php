<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        if (!app()->environment('local', 'testing')) {
            return;
        }

        DB::transaction(function () {
            $user = User::firstOrCreate(
                ['email' => 'demo@example.test'],
                ['password' => Hash::make('bloomify-demo')]
            );

            $plant = $user->plants()->firstOrCreate(
                ['name' => 'Demo fern'],
                ['created_at' => now()]
            );

            $plant->history()->firstOrCreate([], [
                'watered_at' => now()->subDays(8),
                'fertilized_at' => now()->subDays(3),
            ]);
            $plant->needs()->firstOrCreate([], [
                'watering_frequency' => 7,
                'fertilizing_frequency' => 30,
                'need_watering' => true,
            ]);
        });
    }
}
