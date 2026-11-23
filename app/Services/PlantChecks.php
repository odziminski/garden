<?php

namespace App\Services;

use App\Enums\CareType;
use App\Enums\PlantCheckOutcome;
use App\Models\Plant;
use App\Models\PlantCheck;
use App\Models\PlantCheckSchedule;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class PlantChecks
{
    // The caller owns the transaction and has locked an existing plant.
    public function configureLockedPlant(
        Plant $plant,
        bool $enabled,
        ?string $nextCheckOn = null,
        ?int $intervalDays = null,
        bool $useLegacyInterval = false,
    ): PlantCheckSchedule {
        $schedule = $plant->checkSchedule()->firstOrNew();
        $needs = $plant->needs;
        if ($enabled) {
            $legacyInterval = (int) ($needs?->watering_frequency ?? 0);
            if ($legacyInterval > 0) {
                $schedule->legacy_watering_frequency = $legacyInterval;
            }
            if ($useLegacyInterval && $intervalDays === null) {
                $intervalDays = $schedule->legacy_watering_frequency;
                if ($intervalDays === null || $intervalDays > 3650) {
                    throw ValidationException::withMessages(['use_legacy_interval' => 'This plant has no eligible watering interval to copy.']);
                }
            }
            $schedule->interval_days = $intervalDays;
            if ($needs !== null) {
                $needs->watering_frequency = 0;
                $needs->watering_snoozed_until = null;
                PlantCareSchedule::syncFlags($plant, CareType::Watering);
            }
        }

        $schedule->enabled = $enabled;
        $schedule->next_check_on = $enabled ? ($nextCheckOn ?? now($plant->careTimezone())->toDateString()) : null;
        if (!$schedule->exists || $schedule->isDirty()) {
            $this->advance($schedule, $schedule->next_check_on);
        }
        $plant->setRelation('checkSchedule', $schedule);

        return $schedule;
    }

    public function record(
        Plant $plant,
        User $actor,
        PlantCheckOutcome $outcome,
        int $version,
        ?string $requestedDate = null,
        ?string $note = null,
    ): PlantCheck|PlantCheckSchedule|null {
        return DB::transaction(function () use ($plant, $actor, $outcome, $version, $requestedDate, $note) {
            $plant = PlantCareAccess::lock($plant, $actor);
            $schedule = $plant->checkSchedule()->firstOrNew();
            if ($schedule->version !== $version || (!$schedule->isScheduled() && $outcome === PlantCheckOutcome::Skipped)) {
                return null;
            }

            $now = now($plant->careTimezone());
            $today = $now->toDateString();
            $scheduledFor = $schedule->next_check_on;
            $after = $outcome === PlantCheckOutcome::Skipped ? max($today, $scheduledFor) : $today;
            if ($requestedDate !== null && $requestedDate <= $after) {
                throw ValidationException::withMessages(['next_check_on' => 'Choose a later date for the next check.']);
            }
            $next = $scheduledFor === null
                ? null
                : self::nextDate($outcome, $today, $scheduledFor, $schedule->interval_days, $requestedDate);
            if ($next !== null && strlen($next) !== 10) {
                throw ValidationException::withMessages(['next_check_on' => 'Choose a new date within the supported calendar.']);
            }

            if ($outcome === PlantCheckOutcome::Skipped) {
                $this->advance($schedule, $next, $scheduledFor, $actor);

                return $schedule;
            }

            $care = $outcome === PlantCheckOutcome::Watered
                ? PlantCareHistory::append($plant, $actor, CareType::Watering, $now, $today, $note, clearSnooze: true)
                : null;
            $check = $plant->plantChecks()->create([
                'checked_by_user_id' => $actor->getKey(),
                'care_log_id' => $care?->getKey(),
                'outcome' => $outcome,
                'scheduled_for' => $scheduledFor,
                'schedule_version' => $version,
                'checked_on' => $today,
                'checked_at' => $now->copy()->utc(),
                'next_check_on' => $next,
                'note' => $note,
            ]);
            $this->advance($schedule, $next);

            return $check;
        }, 3);
    }

    public function undo(Plant $plant, User $actor, string $id, bool $historyOnly = false): bool
    {
        return DB::transaction(function () use ($plant, $actor, $id, $historyOnly) {
            $plant = PlantCareAccess::lock($plant, $actor);
            $check = $plant->plantChecks()->findOrFail($id);
            abort_unless($check->canBeUndoneBy($actor, $plant), 404);
            $schedule = $plant->checkSchedule()->first();
            if ($check->voided_at !== null || (!$historyOnly && $schedule?->version !== $check->schedule_version + 1)) {
                return false;
            }

            $check->voided_at = now();
            $check->save();
            if ($check->care_log_id !== null) {
                PlantCareHistory::void($plant, $actor, $check->care_log_id);
                PlantCareSchedule::syncFlags($plant, CareType::Watering);
            }
            if (!$historyOnly) {
                $this->advance($schedule, $check->scheduled_for);
            }

            return true;
        }, 3);
    }

    public function undoSkip(Plant $plant, User $actor, int $version): bool
    {
        return DB::transaction(function () use ($plant, $actor, $version) {
            $plant = PlantCareAccess::lock($plant, $actor);
            $schedule = $plant->checkSchedule()->first();
            if (!$schedule?->enabled || $schedule->version !== $version || $schedule->skip_previous_on === null) {
                return false;
            }

            abort_unless($plant->isOwnedBy($actor) || (int) $schedule->skipped_by_user_id === (int) $actor->getKey(), 404);
            $this->advance($schedule, $schedule->skip_previous_on);

            return true;
        }, 3);
    }

    public static function nextDate(
        PlantCheckOutcome $outcome,
        string $today,
        string $scheduledFor,
        ?int $intervalDays,
        ?string $requestedDate = null,
    ): ?string {
        return $requestedDate ?? match ($outcome) {
            PlantCheckOutcome::Skipped => CarbonImmutable::parse(max($today, $scheduledFor), 'UTC')->addDay()->toDateString(),
            PlantCheckOutcome::StillMoist => CarbonImmutable::parse($today, 'UTC')->addDays(2)->toDateString(),
            PlantCheckOutcome::Watered => $intervalDays === null
                ? null
                : CarbonImmutable::parse($today, 'UTC')->addDays($intervalDays)->toDateString(),
        };
    }

    private function advance(PlantCheckSchedule $schedule, ?string $next, ?string $skippedFrom = null, ?User $actor = null): void
    {
        $schedule->next_check_on = $next;
        $schedule->skip_previous_on = $skippedFrom;
        $schedule->skipped_by_user_id = $actor?->getKey();
        $schedule->version++;
        $schedule->save();
    }
}
