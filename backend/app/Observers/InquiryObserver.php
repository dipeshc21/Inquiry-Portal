<?php

namespace App\Observers;

use App\Models\Inquiry;
use App\Services\DashboardService;
use App\Services\ReferenceNumberService;
use App\Support\InquiryOptions;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Throwable;

class InquiryObserver
{
    public function __construct(
        private readonly ReferenceNumberService $references,
        private readonly DashboardService $dashboard
    ) {
    }

    public function creating(Inquiry $inquiry): void
    {
        if (empty($inquiry->reference_no)) {
            $date = $inquiry->created_at !== null
                ? CarbonImmutable::instance($inquiry->created_at)
                : CarbonImmutable::now();

            $inquiry->reference_no = $this->references->next($date);
        }

        $inquiry->status ??= 'new';
        $inquiry->source ??= 'website';
        $inquiry->priority ??= 'medium';

        if (InquiryOptions::isClosed($inquiry->status)) {
            $inquiry->closed_at ??= now();
        } else {
            $inquiry->closed_at = null;
        }
    }

    public function updating(Inquiry $inquiry): void
    {
        if (! $inquiry->isDirty('status')) {
            return;
        }

        if (! InquiryOptions::isClosed($inquiry->status)) {
            $inquiry->closed_at = null;

            return;
        }

        $previousStatus = (string) $inquiry->getOriginal('status');

        if (
            ! InquiryOptions::isClosed($previousStatus)
            || $inquiry->closed_at === null
        ) {
            $inquiry->closed_at = now();
        }
    }

    public function saved(Inquiry $inquiry): void
    {
        $this->invalidateAfterCommit();
    }

    public function deleted(Inquiry $inquiry): void
    {
        $this->invalidateAfterCommit();
    }

    public function restored(Inquiry $inquiry): void
    {
        $this->invalidateAfterCommit();
    }

    private function invalidateAfterCommit(): void
    {
        DB::afterCommit(function (): void {
            try {
                $this->dashboard->invalidate();
            } catch (Throwable $exception) {
                // The mutation is already committed. Report cache failures
                // without incorrectly telling the caller that saving failed.
                // Existing dashboard entries expire after 60 seconds.
                report($exception);
            }
        });
    }
}
