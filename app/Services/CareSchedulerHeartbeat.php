<?php

namespace App\Services;

use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

class CareSchedulerHeartbeat
{
    public const DISCOVERY = 'discovery';
    public const DELIVERY = 'delivery';

    public function record(string $name): void
    {
        DB::table('care_scheduler_heartbeats')->upsert([
            ['name' => $name, 'checked_at' => CarbonImmutable::now('UTC')],
        ], ['name'], ['checked_at']);
    }

    /** @return list<string> */
    public function problems(): array
    {
        $now = CarbonImmutable::now('UTC');
        $problems = [];
        $heartbeats = DB::table('care_scheduler_heartbeats')->pluck('checked_at', 'name');
        foreach ([self::DISCOVERY => 10, self::DELIVERY => 5] as $name => $maxAge) {
            $checkedAt = $heartbeats[$name] ?? null;
            if ($checkedAt === null || CarbonImmutable::parse($checkedAt, 'UTC')->lt($now->subMinutes($maxAge))) {
                $problems[] = "Care reminder {$name} heartbeat is missing or stale.";
            }
        }

        $overdue = DB::table('care_reminder_deliveries')->where('status', 'pending')
            ->where('available_at', '<', $now->subMinutes(10))->count();
        if ($overdue > 0) {
            $problems[] = "{$overdue} care reminder deliveries are more than 10 minutes overdue.";
        }

        $failed = DB::table('care_reminder_deliveries')->where('status', 'failed')
            ->where('finished_at', '>=', $now->subDay())->count();
        if ($failed > 0) {
            $problems[] = "{$failed} care reminder deliveries exhausted retries in the last 24 hours.";
        }

        return $problems;
    }
}
