<?php

namespace App\Services;

use App\Enums\CareType;
use App\Models\CareLog;
use App\Models\Plant;
use App\Models\User;
use Carbon\CarbonInterface;

final class PlantCareHistory
{
    // The caller holds the plant lock and owns the enclosing transaction.
    public static function append(
        Plant $plant,
        User $actor,
        CareType $type,
        CarbonInterface $performedAt,
        string $performedOn,
        ?string $note = null,
        ?string $corrects = null,
        bool $clearSnooze = false,
        ?string $fertilizer = null,
        ?string $dose = null,
    ): CareLog {
        $history = $plant->history()->firstOrNew();
        $column = $type->lastCareColumn();
        $dateColumn = $type->lastCareDateColumn();
        $firstLog = !$plant->careLogs()->where('type', $type->value)->exists();
        $entry = $plant->careLogs()->create([
            'type' => $type,
            'performed_at' => $performedAt->copy()->utc(),
            'performed_on' => $performedOn,
            'note' => $note,
            'fertilizer' => $fertilizer,
            'dose' => $dose,
            'performed_by_user_id' => $actor->getKey(),
            'previous_performed_at' => $firstLog ? $history->$column : null,
            'previous_performed_on' => $firstLog ? $history->$dateColumn : null,
            'corrects_entry_id' => $corrects,
        ]);

        $newerInstant = $history->$column === null || $performedAt->gt($history->$column);
        if ($newerInstant) {
            $history->$column = $performedAt->copy()->utc();
        }
        if ($history->$dateColumn === null ? $newerInstant : $performedOn > $history->$dateColumn) {
            $history->$dateColumn = $performedOn;
        }
        if ($history->isDirty()) {
            $history->save();
        }

        $plant->setRelation('history', $history);
        if ($clearSnooze && $plant->needs) {
            $plant->needs->setAttribute($type->snoozeColumn(), null);
        }
        PlantCareSchedule::syncFlags($plant);

        return $entry;
    }

    public static function void(Plant $plant, User $actor, string|int $entry): bool
    {
        $log = $plant->careLogs()->findOrFail($entry);
        abort_unless($log->canBeCorrectedBy($actor, $plant), 404);
        if ($log->voided_at !== null) {
            return false;
        }
        $log->assertCanChangeIndependently();

        $log->voided_at = now();
        $log->save();

        $logs = $plant->careLogs()->where('type', $log->type->value);
        $original = (clone $logs)->oldest('id')->first(['previous_performed_at', 'previous_performed_on']);
        $active = $logs->whereNull('voided_at');
        $lastInstant = (clone $active)->max('performed_at');
        $lastDay = $active->where('is_legacy', false)->max('performed_on');
        $history = $plant->history()->firstOrNew();
        $history->fill([
            $log->type->lastCareColumn() => max($lastInstant, $original->previous_performed_at?->toDateTimeString()),
            $log->type->lastCareDateColumn() => max($lastDay, $original->previous_performed_on),
        ])->save();

        $plant->setRelation('history', $history);

        return true;
    }
}
