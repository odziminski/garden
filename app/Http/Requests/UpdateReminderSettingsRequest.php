<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateReminderSettingsRequest extends FormRequest
{
    protected $errorBag = 'reminders';

    public function rules(): array
    {
        return [
            'reminders_enabled' => ['required', 'boolean'],
            'reminder_timezone' => ['required', 'string', 'max:64', 'timezone:all'],
            'reminder_time' => ['required', 'date_format:H:i'],
        ];
    }
}
