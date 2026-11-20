<?php

namespace App\Console\Commands;

use App\Mail\DailyCareDigest;
use App\Models\User;
use App\Services\PlantCareSchedule;
use App\Services\ReminderTime;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Mail;
use Throwable;

class SendCareReminders extends Command
{
    protected $signature = 'email:care';

    protected $description = 'Send one daily care email to each owner with plants that need care.';

    public function handle(PlantCareSchedule $schedule): int
    {
        $instant = now();
        $schedule->refreshDueFlags(asOf: $instant);
        $failed = false;
        $due = static function (Builder $needs): void {
            $needs->where(fn (Builder $flags) => $flags->where('need_watering', true)->orWhere('need_fertilizing', true));
        };

        $dueTasks = static fn (string $day) => static fn ($tasks) => $tasks->active()->where('due_on', '<=', $day);
        $latestPossibleDueDate = $instant->copy()->utc()->addDay()->toDateString();
        $recipients = User::query()->where('reminders_enabled', true)
            ->where(fn (Builder $users) => $users->whereHas('plants.needs', $due)
                ->orWhereHas('plants.careTasks', $dueTasks($latestPossibleDueDate)))
            ->lazyById();

        foreach ($recipients as $recipient) {
            $previousDate = $recipient->reminder_sent_on;
            $date = ReminderTime::dueDate($instant, $recipient->reminder_timezone, $recipient->reminder_time, $previousDate);
            if ($date === null) {
                continue;
            }

            $tasksDueToday = $dueTasks($date);
            $plants = $recipient->plants()
                ->where(fn (Builder $plants) => $plants->whereHas('needs', $due)->orWhereHas('careTasks', $tasksDueToday))
                ->with(['needs', 'careTasks' => $tasksDueToday])
                ->orderBy('name')->orderBy('id')->get();
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
