<?php

namespace App\Listeners;

use App\Events\InquiryCreated;
use App\Services\ActivityLogger;

class LogActivity
{
    public function __construct(
        private readonly ActivityLogger $activityLogger
    ) {
    }

    public function handle(InquiryCreated $event): void
    {
        $inquiry = $event->inquiry;

        $this->activityLogger->log(
            action: 'created',
            description: 'Inquiry received through the '
                .$inquiry->source.' channel.',
            inquiry: $inquiry,
            newValues: [
                'reference_no' => $inquiry->reference_no,
                'name' => $inquiry->name,
                'email' => $inquiry->email,
                'subject' => $inquiry->subject,
                'source' => $inquiry->source,
                'status' => $inquiry->status,
                'priority' => $inquiry->priority,
                'assigned_to' => $inquiry->assigned_to,
            ]
        );
    }
}
