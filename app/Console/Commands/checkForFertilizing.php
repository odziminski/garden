<?php

namespace App\Console\Commands;

use App\Services\PlantCareSchedule;
use Illuminate\Console\Command;

class checkForFertilizing extends Command
{
    protected $signature = 'checkForFertilizing';

    protected $description = 'Mark plants that need fertilizing.';

    public function handle(PlantCareSchedule $schedule): int
    {
        $schedule->markFertilizingDue();

        return self::SUCCESS;
    }
}
