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
        $plants = Plant::query()->visibleTo($recipient)
            ->where(fn (Builder $plants) => $plants->where('user_id', $recipient->id)
                ->orWhereHas('careTasks', fn (Builder $query) => $tasks($query)->where('assignee_id', $recipient->id)))
            ->with(['owner', 'needs', 'history', 'careTasks' => $tasks])
            ->lazyById(100);

        $due = new Collection();
        foreach ($plants as $plant) {
            $owned = $plant->isOwnedBy($recipient);
            $today = $instant->copy()->setTimezone($plant->careTimezone())->toDateString();
            $dueTasks = $plant->careTasks->filter(fn (PlantTask $task) => $task->effectiveDueOn() <= $today
                && ($owned || (string) $task->assignee_id === (string) $recipient->id));
            $plant->setRelation('careTasks', $dueTasks);

            if (!$owned) {
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

            if ($scheduledDue || $dueTasks->isNotEmpty()) {
                $due->push($plant);
            }
        }

        return $due->sortBy([['name', 'asc'], ['id', 'asc']])->values();
    }
}
