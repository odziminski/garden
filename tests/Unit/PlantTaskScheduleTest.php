<?php

namespace Tests\Unit;

use App\Services\PlantTaskSchedule;
use Carbon\Carbon;
use PHPUnit\Framework\TestCase;

class PlantTaskScheduleTest extends TestCase
{
    public function test_repeat_interval_uses_calendar_days_across_a_daylight_saving_transition(): void
    {
        $completed = Carbon::parse('2026-03-07 23:30:00', 'America/Los_Angeles');

        $this->assertSame('2026-03-09', PlantTaskSchedule::nextDueOn($completed, 2));
        $this->assertSame('2026-03-07 23:30:00', $completed->toDateTimeString());
    }

    public function test_one_time_task_has_no_next_date(): void
    {
        $this->assertNull(PlantTaskSchedule::nextDueOn(Carbon::parse('2026-11-20'), null));
    }
}
