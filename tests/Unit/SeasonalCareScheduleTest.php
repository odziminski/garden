<?php

namespace Tests\Unit;

use App\Models\History;
use App\Models\Needs;
use App\Models\Plant;
use App\Models\User;
use Carbon\Carbon;
use Tests\TestCase;

class SeasonalCareScheduleTest extends TestCase
{
    public function test_schedule_uses_the_active_season_in_the_owners_time_zone(): void
    {
        $plant = $this->plant('Europe/Warsaw');

        $beforeOctober = Carbon::parse('2026-09-30 21:59:59 UTC');
        $startOfOctober = Carbon::parse('2026-09-30 22:00:00 UTC');

        $this->assertSame('2026-09-28', $plant->nextWatering($beforeOctober)->toDateString());
        $this->assertSame('2026-10-03', $plant->nextWatering($startOfOctober)->toDateString());
        $this->assertSame('2026-10-06', $plant->nextFertilizing($beforeOctober)->toDateString());
        $this->assertSame('2026-10-16', $plant->nextFertilizing($startOfOctober)->toDateString());
    }

    public function test_default_interval_handles_local_calendar_days_through_daylight_saving(): void
    {
        $plant = $this->plant('Europe/Warsaw');
        $plant->history->watered_at = Carbon::parse('2026-03-27 23:30:00 UTC');
        $plant->needs->winter_watering_frequency = null;
        $plant->needs->watering_frequency = 2;
        $asOf = Carbon::parse('2026-03-29 22:00:00 UTC');

        $this->assertSame('2026-03-30 00:00:00 +02:00', $plant->nextWatering($asOf)->format('Y-m-d H:i:s P'));
    }

    public function test_schedule_can_begin_from_the_day_the_plant_was_added(): void
    {
        $plant = $this->plant('UTC');
        $plant->history->watered_at = null;
        $plant->created_at = Carbon::parse('2026-04-01 10:00 UTC');

        $this->assertSame('2026-04-03', $plant->nextWatering(Carbon::parse('2026-04-01 UTC'))->toDateString());

        $plant->created_at = null;
        $this->assertNull($plant->nextWatering(Carbon::parse('2026-04-01 UTC')));
    }

    private function plant(string $timezone): Plant
    {
        $owner = new User;
        $owner->reminder_timezone = $timezone;
        $lastCare = Carbon::parse('2026-09-26 09:00:00 UTC');

        return (new Plant)
            ->setRelation('owner', $owner)
            ->setRelation('history', new History(['watered_at' => $lastCare, 'fertilized_at' => $lastCare]))
            ->setRelation('needs', new Needs([
                'watering_frequency' => 4,
                'fertilizing_frequency' => 10,
                'summer_watering_frequency' => 2,
                'winter_watering_frequency' => 7,
                'winter_fertilizing_frequency' => 20,
            ]));
    }
}
