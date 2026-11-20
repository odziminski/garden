<?php

namespace Tests\Unit;

use App\Enums\PlantCheckOutcome;
use App\Services\PlantChecks;
use PHPUnit\Framework\TestCase;

class PlantCheckDatesTest extends TestCase
{
    public function test_next_check_uses_outcome_specific_calendar_rules(): void
    {
        $this->assertSame('2026-03-29', PlantChecks::nextDate(PlantCheckOutcome::Skipped, '2026-03-28', '2026-03-25', 7));
        $this->assertSame('2026-03-31', PlantChecks::nextDate(PlantCheckOutcome::Skipped, '2026-03-28', '2026-03-30', 7));
        $this->assertSame('2026-03-30', PlantChecks::nextDate(PlantCheckOutcome::StillMoist, '2026-03-28', '2026-03-25', 7));
        $this->assertSame('2026-04-02', PlantChecks::nextDate(PlantCheckOutcome::StillMoist, '2026-03-28', '2026-03-25', 7, '2026-04-02'));
        $this->assertSame('2026-04-04', PlantChecks::nextDate(PlantCheckOutcome::Watered, '2026-03-28', '2026-03-25', 7));
        $this->assertNull(PlantChecks::nextDate(PlantCheckOutcome::Watered, '2026-03-28', '2026-03-25', null));
    }
}
