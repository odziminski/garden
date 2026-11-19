<?php

namespace App\Services;

use DateTimeImmutable;
use DateTimeInterface;
use DateTimeZone;

final class ReminderTime
{
    public static function dueDate(
        DateTimeInterface $instant,
        string $timezone,
        string $time,
        ?string $lastSentOn,
    ): ?string {
        $local = DateTimeImmutable::createFromInterface($instant)->setTimezone(new DateTimeZone($timezone));
        $today = $local->format('Y-m-d');

        if ($local->format('H:i') < $time || ($lastSentOn !== null && $lastSentOn >= $today)) {
            return null;
        }

        return $today;
    }
}
