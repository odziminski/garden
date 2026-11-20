<?php

namespace App\Http\Requests;

use App\Services\PlantTaskSchedule;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class StorePlantTaskRequest extends FormRequest
{
    protected $errorBag = 'customTask';

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:120'],
            'due_on' => ['required', 'date_format:Y-m-d', 'after_or_equal:1000-01-01', 'before_or_equal:9999-12-31'],
            'repeat_days' => ['nullable', 'integer', 'min:1', 'max:3300000'],
        ];
    }

    public function after(): array
    {
        return [
            function (Validator $validator): void {
                if ($validator->errors()->isNotEmpty() || $this->input('repeat_days') === null) {
                    return;
                }

                $timezone = $this->route('plant')->careTimezone();
                $firstCompletionOn = max($this->input('due_on'), now($timezone)->toDateString());
                $nextDueOn = PlantTaskSchedule::nextDueOn(
                    CarbonImmutable::parse($firstCompletionOn, $timezone),
                    $this->integer('repeat_days'),
                );

                if ($nextDueOn === null) {
                    $validator->errors()->add('repeat_days', 'Choose an interval that keeps the next due date within the calendar.');
                }
            },
        ];
    }
}
