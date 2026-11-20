<?php

namespace App\Console\Commands;

use App\Services\CareSchedulerHeartbeat;
use Illuminate\Console\Command;

class CareSchedulerStatus extends Command
{
    protected $signature = 'care:status';

    protected $description = 'Check care reminder scheduler heartbeats and delivery health';

    public function handle(CareSchedulerHeartbeat $heartbeat): int
    {
        $problems = $heartbeat->problems();
        foreach ($problems as $problem) {
            $this->error($problem);
        }

        if ($problems !== []) {
            return self::FAILURE;
        }

        $this->info('Care reminder scheduling and delivery are current.');
        return self::SUCCESS;
    }
}
