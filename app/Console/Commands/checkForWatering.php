<?php

namespace App\Console\Commands;

use App\Services\PlantCareSchedule;
use Illuminate\Console\Command;

class checkForWatering extends Command
{
    protected $signature = 'checkForWatering';

    protected $description = 'Mark plants that need watering.';

    public function handle(PlantCareSchedule $schedule): int
    {
        $schedule->markWateringDue();

        return self::SUCCESS;
    }
}
