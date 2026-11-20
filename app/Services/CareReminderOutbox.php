<?php

namespace App\Services;

use App\Contracts\CarePushDelivery;
use App\Mail\DailyCareDigest;
use App\Models\PlantTask;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Query\Builder as Query;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Throwable;

class CareReminderOutbox
{
    private const BACKOFF_MINUTES = [5, 15, 30, 60];
    private const LEASE_MINUTES = 5;
    private const MAX_ATTEMPTS = 5;

    public function __construct(private readonly ReminderPlants $plants)
    {
    }

    public function discover(): void
    {
        $instant = CarbonImmutable::now('UTC');
        $latestPossibleDate = $instant->addDay()->toDateString();
        $push = app()->bound(CarePushDelivery::class);
        $recipients = User::query()->whereNotNull('email_verified_at')
            ->where(function (Builder $query) use ($push) {
                $query->where('reminders_enabled', true);
                if ($push) {
                    $query->orWhere('push_reminders_enabled', true);
                }
            })
            ->where(fn (Builder $users) => $users->whereHas('plants')->orWhereExists(
                PlantTask::query()->active()->selectRaw('1')->whereColumn('assignee_id', 'users.id')
                    ->where('due_on', '<=', $latestPossibleDate)->toBase()
            ))
            ->lazyById(100);

        foreach ($recipients as $recipient) {
            $date = ReminderTime::dueDate($instant, $recipient->reminder_timezone, $recipient->reminder_time, null);
            if ($date === null) {
                continue;
            }

            $channels = [];
            if ($recipient->reminders_enabled && ($recipient->reminder_sent_on === null || $recipient->reminder_sent_on < $date)) {
                $channels[] = 'email';
            }
            if ($push && $recipient->push_reminders_enabled) {
                $channels[] = 'push';
            }
            if ($channels === []) {
                continue;
            }

            $alreadyQueued = $this->deliveries()->where('user_id', $recipient->id)->where('local_date', $date)->pluck('channel')->all();
            $channels = array_diff($channels, $alreadyQueued);
            if ($channels === [] || $this->plants->dueFor($recipient, $instant)->isEmpty()) {
                continue;
            }

            $this->deliveries()->insertOrIgnore(array_map(fn (string $channel) => [
                'user_id' => $recipient->id,
                'channel' => $channel,
                'local_date' => $date,
                'available_at' => $instant,
            ], $channels));
        }
    }

    public function deliverPending(): bool
    {
        $instant = CarbonImmutable::now('UTC');
        $failed = $this->deliveries()->where('status', 'pending')->where('available_at', '<=', $instant)
            ->where('attempts', '>=', self::MAX_ATTEMPTS)
            ->update(['status' => 'failed', 'finished_at' => $instant]);
        if ($failed > 0) {
            Log::warning('Care reminder delivery leases expired after the last attempt.', ['deliveries' => $failed]);
        }

        $due = $this->deliveries()->where('status', 'pending')->where('available_at', '<=', $instant)
            ->orderBy('available_at')->orderBy('id')->limit(100)->get();
        $started = hrtime(true);
        foreach ($due as $delivery) {
            if (hrtime(true) - $started >= 45_000_000_000) {
                break;
            }

            if (!$this->deliver($delivery)) {
                $failed++;
            }
        }

        return $failed === 0;
    }

    private function deliver(object $delivery): bool
    {
        $instant = CarbonImmutable::now('UTC');
        $claim = $this->deliveries()->where('id', $delivery->id)->where('status', 'pending');
        $attempt = $delivery->attempts + 1;
        $claimed = (clone $claim)->where('attempts', $delivery->attempts)->where('available_at', '<=', $instant)
            ->update(['attempts' => $attempt, 'available_at' => $instant->addMinutes(self::LEASE_MINUTES)]);
        if ($claimed === 0) {
            return true;
        }

        // An expired worker cannot change the result of a later attempt.
        $claim->where('attempts', $attempt);
        $recipient = User::query()->find($delivery->user_id);
        if (!$this->eligible($recipient, $delivery->channel, $delivery->local_date, $instant)) {
            $claim->update(['status' => 'cancelled', 'finished_at' => $instant]);
            return true;
        }

        if (ReminderTime::dueDate($instant, $recipient->reminder_timezone, $recipient->reminder_time, null) === null) {
            $claim->update(['attempts' => $delivery->attempts, 'available_at' => $instant->addMinutes(5)]);
            return true;
        }

        $plants = $this->plants->dueFor($recipient, $instant);
        if ($plants->isEmpty()) {
            $claim->update(['status' => 'cancelled', 'finished_at' => $instant]);
            return true;
        }

        try {
            if ($delivery->channel === 'email') {
                Mail::to($recipient)->send(new DailyCareDigest($plants));
            } else {
                app(CarePushDelivery::class)->send($recipient, $plants);
            }
        } catch (Throwable $exception) {
            $retryAt = self::BACKOFF_MINUTES[$attempt - 1] ?? null;
            $now = CarbonImmutable::now('UTC');
            $claim->update($retryAt === null
                ? ['status' => 'failed', 'finished_at' => $now]
                : ['available_at' => $now->addMinutes($retryAt)]);
            Log::warning('Care reminder delivery failed.', [
                'delivery_id' => $delivery->id,
                'channel' => $delivery->channel,
                'attempt' => $attempt,
                'error_type' => $exception::class,
            ]);
            return false;
        }

        DB::transaction(function () use ($claim, $delivery) {
            $finished = $claim->update(['status' => 'sent', 'finished_at' => CarbonImmutable::now('UTC')]);
            if ($finished && $delivery->channel === 'email') {
                User::query()->whereKey($delivery->user_id)->where(fn (Builder $users) => $users
                    ->whereNull('reminder_sent_on')->orWhere('reminder_sent_on', '<', $delivery->local_date))
                    ->update(['reminder_sent_on' => $delivery->local_date]);
            }
        }, 3);

        return true;
    }

    private function eligible(?User $recipient, string $channel, string $date, CarbonImmutable $instant): bool
    {
        if ($recipient === null || $recipient->email_verified_at === null
            || $instant->setTimezone($recipient->reminder_timezone)->toDateString() !== $date) {
            return false;
        }

        return match ($channel) {
            'email' => $recipient->reminders_enabled && ($recipient->reminder_sent_on === null || $recipient->reminder_sent_on < $date),
            'push' => app()->bound(CarePushDelivery::class) && $recipient->push_reminders_enabled,
            default => false,
        };
    }

    private function deliveries(): Query
    {
        return DB::table('care_reminder_deliveries');
    }
}
