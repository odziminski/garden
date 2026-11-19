<?php

namespace App\Console\Commands;

use App\Mail\DailyCareDigest;
use App\Models\User;
use App\Services\ReminderTime;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Mail;
use Throwable;

class SendCareReminders extends Command
{
    protected $signature = 'email:care';

    protected $description = 'Send one daily care email to each owner with plants that need care.';

    public function handle(): int
    {
        $instant = now();
        $failed = false;
        $due = static function (Builder $needs): void {
            $needs->where(fn (Builder $flags) => $flags->where('need_watering', true)->orWhere('need_fertilizing', true));
        };

        $recipients = User::query()->where('reminders_enabled', true)->whereHas('plants.needs', $due)->lazyById();

        foreach ($recipients as $recipient) {
            $previousDate = $recipient->reminder_sent_on;
            $date = ReminderTime::dueDate($instant, $recipient->reminder_timezone, $recipient->reminder_time, $previousDate);
            if ($date === null) {
                continue;
            }

            $plants = $recipient->plants()->whereHas('needs', $due)->with('needs')->orderBy('name')->orderBy('id')->get();
            if ($plants->isEmpty()) {
                continue;
            }

            $claimed = User::query()->whereKey($recipient->id)
                ->where('reminders_enabled', true)
                ->where('email', $recipient->email)
                ->where('reminder_timezone', $recipient->reminder_timezone)
                ->where('reminder_time', $recipient->reminder_time)
                ->where('reminder_sent_on', $previousDate)
                ->update(['reminder_sent_on' => $date]);

            if (!$claimed) {
                continue;
            }

            try {
                Mail::to($recipient)->send(new DailyCareDigest($plants));
            } catch (Throwable $exception) {
                User::query()->whereKey($recipient->id)->where('reminder_sent_on', $date)
                    ->update(['reminder_sent_on' => $previousDate]);
                report($exception);
                $failed = true;
            }
        }

        return $failed ? self::FAILURE : self::SUCCESS;
    }
}
