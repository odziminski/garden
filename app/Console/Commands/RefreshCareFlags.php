<?php

namespace App\Console\Commands;

use App\Services\PlantCareSchedule;
use Illuminate\Console\Command;

class RefreshCareFlags extends Command
{
    protected $signature = 'care:refresh';

    protected $description = 'Refresh watering and fertilizing due flags';

    public function handle(PlantCareSchedule $schedule): int
    {
        $schedule->refreshDueFlags();

        return self::SUCCESS;
    }
}
