<?php

namespace App\Http\Controllers;

use App\Http\Requests\UpdateReminderSettingsRequest;
use Illuminate\Http\RedirectResponse;

class ReminderSettingsController extends Controller
{
    public function update(UpdateReminderSettingsRequest $request): RedirectResponse
    {
        $request->user()->forceFill($request->validated())->save();

        return to_route('users')->with('reminder_message', 'Reminder preferences saved.');
    }
}
