<?php

namespace App\Services;

use App\Enums\CareType;
use App\Models\Plant;
use App\Models\PlantTask;
use App\Models\User;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;

class ReminderPlants
{
    /** @return Collection<int, Plant> */
    public function dueFor(User $recipient, CarbonInterface $instant): Collection
    {
        $latestPossibleDate = $instant->copy()->utc()->addDay()->toDateString();
        $tasks = static fn ($tasks) => $tasks->active()->where('due_on', '<=', $latestPossibleDate);
        $handoffPlantIds = Plant::query()->withActiveHandoffFor($recipient, $instant)->pluck('plants.id')->flip();
        $plants = Plant::query()->visibleTo($recipient)
            ->where(fn (Builder $plants) => $plants->where('user_id', $recipient->id)
                ->orWhereIntegerInRaw('plants.id', $handoffPlantIds->keys()->all())
                ->orWhereHas('careTasks', fn (Builder $query) => $tasks($query)->where('assignee_id', $recipient->id)))
            ->with(['owner', 'needs', 'history', 'checkSchedule', 'careTasks' => $tasks])
            ->lazyById(100);

        $due = new Collection();
        foreach ($plants as $plant) {
            $owned = $plant->isOwnedBy($recipient);
            $underHandoff = $handoffPlantIds->has($plant->getKey());
            $today = $instant->copy()->setTimezone($plant->careTimezone())->toDateString();
            $dueTasks = $plant->careTasks->filter(fn (PlantTask $task) => $task->effectiveDueOn() <= $today
                && ($owned || (string) $task->assignee_id === (string) $recipient->id || ($underHandoff && $task->assignee_id === null)));
            $plant->setRelation('careTasks', $dueTasks);

            if (!$owned && !$underHandoff) {
                $plant->setRelation('needs', null);
            }

            $scheduledDue = false;
            if ($plant->needs !== null) {
                foreach (CareType::cases() as $type) {
                    $isDue = PlantCareSchedule::nextDate($plant, $type, $instant)?->lte($instant) ?? false;
                    $plant->needs->setAttribute($type->dueFlag(), $isDue);
                    $scheduledDue = $scheduledDue || $isDue;
                }
            }

            $checkDue = ($owned || $underHandoff) && $plant->checkSchedule?->isScheduled()
                && $plant->checkSchedule->next_check_on <= $today;
            $plant->setAttribute('soil_check_due', $checkDue);

            if ($checkDue || $scheduledDue || $dueTasks->isNotEmpty()) {
                $due->push($plant);
            }
        }

        return $due->sortBy([['name', 'asc'], ['id', 'asc']])->values();
    }
}
