<?php

namespace App\Jobs;

use App\Mail\ReminderNotification;
use App\Models\Reminder;
use App\Services\ActivityLogger;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Mail;

class DeliverReminder implements ShouldBeUnique, ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public int $timeout = 90;

    public int $uniqueFor = 600;

    public function __construct(
        public int $reminderId
    ) {
        $this->afterCommit();
    }

    public function uniqueId(): string
    {
        return 'reminder:'.$this->reminderId;
    }

    public function backoff(): array
    {
        return [15, 60, 180];
    }

    public function handle(ActivityLogger $activityLogger): void
    {
        DB::transaction(function () use ($activityLogger): void {
            $reminder = Reminder::query()
                ->with(['inquiry', 'user'])
                ->lockForUpdate()
                ->find($this->reminderId);

            if (
                $reminder === null
                || $reminder->is_completed
                || $reminder->notified_at !== null
                || $reminder->remind_at->isFuture()
                || $reminder->inquiry === null
                || $reminder->user === null
                || ! $reminder->user->is_active
            ) {
                return;
            }

            // Reassignment can remove an agent's access after a reminder
            // was created. Do not email inquiry details to that agent.
            if (
                Gate::forUser($reminder->user)->denies(
                    'view',
                    $reminder->inquiry
                )
            ) {
                return;
            }

            // This runs inside a queued job. Sending synchronously here lets
            // notified_at reflect successful handoff to the mail transport.
            Mail::to($reminder->user->email)->send(
                new ReminderNotification($reminder)
            );

            $reminder->notified_at = now();
            $reminder->save();

            $activityLogger->log(
                action: 'reminder_notified',
                description: 'Follow-up reminder notification sent.',
                inquiry: $reminder->inquiry,
                newValues: [
                    'reminder_id' => $reminder->id,
                    'recipient_user_id' => $reminder->user_id,
                    'notified_at' => $reminder->notified_at->toIso8601String(),
                ]
            );
        });
    }
}
