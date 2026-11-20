<?php

namespace App\Services;

use App\Enums\PlantObservationCategory;
use App\Models\Plant;
use App\Models\PlantObservation;
use App\Models\PlantPhotoEntry;
use App\Models\PlantTask;
use App\Models\User;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class PlantObservations
{
    public function record(User $actor, Plant $plant, array $input): PlantObservation
    {
        return DB::transaction(function () use ($actor, $plant, $input) {
            $plant = PlantCareAccess::lock($plant, $actor);
            $ownerFields = ['photo_entry_id', 'follow_up_name', 'follow_up_due_on'];
            foreach ($ownerFields as $field) {
                abort_if(!$plant->isOwnedBy($actor) && isset($input[$field]) && $input[$field] !== '', 404);
            }

            $today = now($plant->careTimezone())->toDateString();
            $repotting = PlantObservationCategory::Repotting->value;
            $details = Validator::make($input, [
                'category' => ['required', Rule::enum(PlantObservationCategory::class)],
                'observed_on' => ['bail', 'required', 'date_format:Y-m-d', 'after_or_equal:1000-01-01', 'before_or_equal:'.$today],
                'notes' => ['required', 'string', 'max:1000'],
                'container_size' => ['nullable', 'prohibited_unless:category,'.$repotting, 'string', 'max:100'],
                'substrate' => ['nullable', 'prohibited_unless:category,'.$repotting, 'string', 'max:500'],
                'photo_entry_id' => ['nullable', 'integer', 'min:1'],
                'follow_up_name' => ['nullable', 'required_with:follow_up_due_on', 'string', 'max:120'],
                'follow_up_due_on' => ['bail', 'nullable', 'required_with:follow_up_name', 'date_format:Y-m-d', 'after_or_equal:'.$today, 'before_or_equal:9999-12-31'],
            ], [
                'container_size.prohibited_unless' => 'Container size can only be added to a repotting observation.',
                'substrate.prohibited_unless' => 'Substrate can only be added to a repotting observation.',
            ], [
                'observed_on' => 'observation date',
                'photo_entry_id' => 'journal photo',
                'follow_up_due_on' => 'follow-up date',
                'follow_up_name' => 'follow-up name',
            ])->validate();

            if (isset($details['photo_entry_id'])) {
                $photo = PlantPhotoEntry::query()->forPlant($plant)->lockForUpdate()->find($details['photo_entry_id']);
                if ($photo === null) {
                    throw ValidationException::withMessages(['photo_entry_id' => 'Choose a journal photo from this plant.']);
                }
            }

            $task = isset($details['follow_up_name'])
                ? PlantTask::query()->create([
                    'plant_id' => $plant->getKey(),
                    'name' => $details['follow_up_name'],
                    'due_on' => $details['follow_up_due_on'],
                    'repeat_days' => null,
                ])
                : null;

            return PlantObservation::query()->create(Arr::only($details, [
                'category', 'observed_on', 'notes', 'container_size', 'substrate',
            ]) + [
                'plant_id' => $plant->getKey(),
                'actor_id' => $actor->getKey(),
                'photo_entry_id' => isset($photo) ? $photo->getKey() : null,
                'follow_up_task_id' => $task?->getKey(),
            ]);
        }, 3);
    }
}
