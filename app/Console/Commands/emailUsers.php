<?php

namespace App\Console\Commands;

use App\Mail\TakeCareOfPlant;
use App\Models\Plant;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Mail;

class emailUsers extends Command
{
    protected $signature = 'email:care';

    protected $description = 'Email owners about plants that need care.';

    public function handle(): int
    {
        $plants = Plant::query()->with('owner')
            ->whereHas('needs', function ($query) {
                $query->where('need_watering', true)->orWhere('need_fertilizing', true);
            })
            ->lazyById();

        foreach ($plants as $plant) {
            if ($plant->owner) {
                Mail::to($plant->owner)->send(new TakeCareOfPlant($plant));
            }
        }

        return self::SUCCESS;
    }
}
