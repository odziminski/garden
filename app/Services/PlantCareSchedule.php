<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;

class PlantCareSchedule
{
    public function markWateringDue(): void
    {
        $this->markDue('need_watering', 'watered_at', 'watering_frequency');
    }

    public function markFertilizingDue(): void
    {
        $this->markDue('need_fertilizing', 'fertilized_at', 'fertilizing_frequency');
    }

    private function markDue(string $flag, string $lastCare, string $frequency): void
    {
        $date = match (DB::connection()->getDriverName()) {
            'sqlite' => "datetime(history.$lastCare, '+' || needs.$frequency || ' days')",
            'mysql', 'mariadb' => "date_add(history.$lastCare, interval needs.$frequency day)",
            'pgsql' => "history.$lastCare + needs.$frequency * interval '1 day'",
            'sqlsrv' => "dateadd(day, needs.$frequency, history.$lastCare)",
        };

        DB::table('needs')
            ->where($flag, false)
            ->where($frequency, '>', 0)
            ->whereExists(function ($query) use ($date) {
                $query->selectRaw('1')
                    ->from('history')
                    ->whereColumn('history.plant_id', 'needs.plant_id')
                    ->whereRaw("$date <= ?", [now()->toDateTimeString()]);
            })
            ->update([$flag => true]);
    }
}
