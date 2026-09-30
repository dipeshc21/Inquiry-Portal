<?php

namespace App\Listeners;

use App\Events\InquiryCreated;
use App\Mail\InquiryAcknowledgement;
use App\Mail\NewInquiryNotification;
use App\Models\User;
use Illuminate\Contracts\Queue\ShouldQueueAfterCommit;
use Illuminate\Support\Facades\Mail;

class SendNewInquiryNotification implements ShouldQueueAfterCommit
{
    public int $tries = 3;

    public int $timeout = 60;

    public function backoff(): array
    {
        return [15, 60, 180];
    }

    public function handle(InquiryCreated $event): void
    {
        $inquiry = $event->inquiry->fresh();

        if ($inquiry === null) {
            return;
        }

        $recipients = User::query()
            ->active()
            ->where(function ($query) use ($inquiry): void {
                $query->whereIn('role', ['admin', 'manager']);

                if ($inquiry->assigned_to !== null) {
                    $query->orWhere('id', $inquiry->assigned_to);
                }
            })
            ->get(['id', 'email']);

        foreach ($recipients as $recipient) {
            Mail::to($recipient->email)->queue(
                new NewInquiryNotification($inquiry)
            );
        }

        Mail::to($inquiry->email)->queue(
            new InquiryAcknowledgement($inquiry)
        );
    }
}
