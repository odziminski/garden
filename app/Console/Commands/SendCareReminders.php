<?php

namespace App\Console\Commands;

use App\Services\CareReminderOutbox;
use App\Services\CareSchedulerHeartbeat;
use Illuminate\Console\Command;

class SendCareReminders extends Command
{
    protected $signature = 'email:care
        {--discover-only : Enqueue due reminders without delivering them}
        {--pending-only : Deliver queued reminders without discovering new ones}
        {--scheduled : Record scheduler heartbeats}';

    protected $description = 'Enqueue and deliver due care reminders';

    public function handle(CareReminderOutbox $outbox, CareSchedulerHeartbeat $heartbeat): int
    {
        if ($this->option('discover-only') && $this->option('pending-only')) {
            $this->error('Choose one reminder phase at a time.');
            return self::INVALID;
        }

        if (!$this->option('pending-only')) {
            $outbox->discover();
            if ($this->option('scheduled')) {
                $heartbeat->record(CareSchedulerHeartbeat::DISCOVERY);
            }
        }

        $success = true;
        if (!$this->option('discover-only')) {
            $success = $outbox->deliverPending();
            if ($this->option('scheduled')) {
                $heartbeat->record(CareSchedulerHeartbeat::DELIVERY);
            }
        }

        return $success ? self::SUCCESS : self::FAILURE;
    }
}
