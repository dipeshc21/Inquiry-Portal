<?php

namespace App\Support;

final class InquiryOptions
{
    public const ROLES = [
        'admin',
        'manager',
        'agent',
    ];

    public const STATUSES = [
        'new',
        'contacted',
        'qualified',
        'pending',
        'won',
        'lost',
    ];

    public const OPEN_STATUSES = [
        'new',
        'contacted',
        'qualified',
    ];

    public const CLOSED_STATUSES = [
        'won',
        'lost',
    ];

    public const SOURCES = [
        'website',
        'campaign',
        'referral',
        'social_media',
        'email',
        'other',
    ];

    public const PRIORITIES = [
        'low',
        'medium',
        'high',
    ];

    public const ASSIGNMENT_STRATEGIES = [
        'round_robin',
        'least_loaded',
    ];

    public const SORT_COLUMNS = [
        'created_at',
        'name',
        'status',
        'priority',
    ];

    public const PHONE_PATTERN = '/^\+?[0-9\s().\-]{7,25}$/';

    public static function isClosed(string $status): bool
    {
        return in_array($status, self::CLOSED_STATUSES, true);
    }
}
