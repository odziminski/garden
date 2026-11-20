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
            'name' => ['required', 'string', 'min:3', 'max:64'],
            'watering_frequency' => ['nullable', 'integer', 'min:1', 'max:3650'],
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

    public function validatedSchedule(): array
    {
        $schedule = $this->safe()->only(Needs::SCHEDULE_FIELDS);
        foreach (['watering_frequency' => Needs::DEFAULT_WATERING_DAYS, 'fertilizing_frequency' => 0] as $field => $default) {
            if ($this->isMethod('POST') || array_key_exists($field, $schedule)) {
                $schedule[$field] ??= $default;
            }
        }

        return $schedule;
    }
}
