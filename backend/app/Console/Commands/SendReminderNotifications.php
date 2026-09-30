<?php

namespace App\Console\Commands;

use App\Jobs\DeliverReminder;
use App\Models\Reminder;
use Illuminate\Console\Command;

class SendReminderNotifications extends Command
{
    protected $signature = 'reminders:send';

    protected $description = 'Queue notifications for due inquiry reminders';

    public function handle(): int
    {
        $queued = 0;

        Reminder::query()
            ->due()
            ->select('id')
            ->chunkById(200, function ($reminders) use (&$queued): void {
                foreach ($reminders as $reminder) {
                    DeliverReminder::dispatch($reminder->id);
                    $queued++;
                }
            });

        $this->info(
            "Processed {$queued} due reminder(s) for notification dispatch."
        );

        return self::SUCCESS;
    }
}
