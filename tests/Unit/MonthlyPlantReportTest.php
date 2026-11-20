<?php

namespace Tests\Unit;

use App\Services\MonthlyPlantReport;
use PHPUnit\Framework\TestCase;

class MonthlyPlantReportTest extends TestCase
{
    public function test_intervals_use_distinct_calendar_dates_and_the_previous_watering(): void
    {
        $report = new MonthlyPlantReport;

        self::assertSame([
            ['from' => '2024-02-27', 'to' => '2024-03-02', 'days' => 4],
            ['from' => '2024-03-02', 'to' => '2024-03-31', 'days' => 29],
            ['from' => '2024-03-31', 'to' => '2024-04-01', 'days' => 1],
        ], $report->wateringIntervals([
            '2024-03-31', '2024-03-02', '2024-03-02', '2024-04-01',
        ], '2024-02-27'));
    }

    public function test_intervals_require_two_distinct_watering_dates(): void
    {
        $report = new MonthlyPlantReport;

        self::assertSame([], $report->wateringIntervals([], '2024-02-27'));
        self::assertSame([], $report->wateringIntervals(['2024-03-02', '2024-03-02']));
        self::assertSame([
            ['from' => '2024-03-02', 'to' => '2024-03-06', 'days' => 4],
        ], $report->wateringIntervals(['2024-03-02', '2024-03-06']));
    }
}
