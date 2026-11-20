<?php

namespace App\Http\Requests;

use App\Models\Needs;
use App\Services\PlantPhoto;
use Closure;
use Illuminate\Foundation\Http\FormRequest;

class StorePlantRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:64'],
            'room' => ['nullable', 'string', 'max:80'],
            'species_name' => ['nullable', 'string', 'max:120'],
            'care_instructions' => ['nullable', 'string', 'max:600'],
            'first_check_on' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:'.now($this->user()->reminder_timezone ?? 'UTC')->toDateString(), 'before_or_equal:9999-12-31'],
            'check_interval_days' => ['nullable', 'integer', 'min:1', 'max:3650'],
            'watering_frequency' => ['nullable', 'integer', 'min:0', 'max:3650'],
            'fertilizing_frequency' => ['nullable', 'integer', 'min:0', 'max:3650'],
            'seasonal_hemisphere' => ['sometimes', 'required', 'in:north,south'],
            'summer_watering_frequency' => ['nullable', 'integer', 'min:1', 'max:3650'],
            'winter_watering_frequency' => ['nullable', 'integer', 'min:1', 'max:3650'],
            'summer_fertilizing_frequency' => ['nullable', 'integer', 'min:1', 'max:3650'],
            'winter_fertilizing_frequency' => ['nullable', 'integer', 'min:1', 'max:3650'],
            'avatar' => ['nullable', 'file', 'mimes:jpg,jpeg,png,webp', 'max:5120'],
            'webcamAvatar' => [
                'bail',
                'nullable',
                'string',
                'max:6990532',
                function (string $attribute, string $value, Closure $fail): void {
                    if (!PlantPhoto::isValidWebcamImage($value)) {
                        $fail('The webcam photo must be a JPEG, PNG or WebP image of up to 5 MB.');
                    }
                },
            ],
        ];
    }

    public function validatedProfile(): array
    {
        return $this->safe()->only(['name', 'room', 'species_name', 'care_instructions']);
    }

    public function validatedSchedule(): array
    {
        $schedule = $this->safe()->only(Needs::SCHEDULE_FIELDS);
        foreach (['watering_frequency', 'fertilizing_frequency'] as $field) {
            if ($this->isMethod('POST') || array_key_exists($field, $schedule)) {
                $schedule[$field] ??= 0;
            }
        }

        return $schedule;
    }
}
