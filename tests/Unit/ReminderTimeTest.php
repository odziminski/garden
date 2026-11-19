<?php

namespace Tests\Unit;

use App\Services\ReminderTime;
use DateTimeImmutable;
use PHPUnit\Framework\TestCase;

class ReminderTimeTest extends TestCase
{
    public function test_reminder_uses_the_recipients_local_date_and_time(): void
    {
        $before = new DateTimeImmutable('2026-11-20T09:09:59+00:00');
        $atTime = new DateTimeImmutable('2026-11-20T09:10:00+00:00');
        $afterMidnight = new DateTimeImmutable('2026-11-20T23:20:00+00:00');

        $this->assertNull(ReminderTime::dueDate($before, 'Europe/Warsaw', '10:10', null));
        $this->assertSame('2026-11-20', ReminderTime::dueDate($atTime, 'Europe/Warsaw', '10:10', '2026-11-19'));
        $this->assertNull(ReminderTime::dueDate($atTime, 'Europe/Warsaw', '10:10', '2026-11-20'));
        $this->assertSame('2026-11-21', ReminderTime::dueDate($afterMidnight, 'Europe/Warsaw', '00:15', '2026-11-20'));
    }

    public function test_reminder_can_run_after_a_clock_change_skips_the_chosen_local_time(): void
    {
        $instant = new DateTimeImmutable('2026-03-29T01:10:00+00:00');

        $this->assertSame('2026-03-29', ReminderTime::dueDate($instant, 'Europe/Warsaw', '02:30', null));
        $this->assertNull(ReminderTime::dueDate($instant, 'Europe/Warsaw', '02:30', '2026-03-29'));
    }
}
