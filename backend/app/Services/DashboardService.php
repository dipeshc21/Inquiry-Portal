<?php

namespace App\Services;

use App\Models\Inquiry;
use App\Models\User;
use App\Support\InquiryOptions;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;

class DashboardService
{
    private const GENERATION_KEY = 'dashboard:generation';

    public function stats(User $user): array
    {
        $generation = Cache::get(self::GENERATION_KEY, 'initial');
        $date = CarbonImmutable::today()->format('Y-m-d');

        // Include the role so a role change cannot reuse a broader cache.
        $key = sprintf(
            'dashboard:%s:%s:%s:%d',
            $generation,
            $date,
            $user->role,
            $user->id
        );

        return Cache::remember(
            $key,
            (int) config('inquiry.dashboard_cache_seconds', 60),
            fn (): array => $this->calculate($user)
        );
    }

    public function invalidate(): void
    {
        // A generation key invalidates all users without scanning cache keys.
        // An in-flight reader may finish writing an old generation, but future
        // readers will never use that generation.
        Cache::forever(self::GENERATION_KEY, (string) Str::uuid());
    }

    private function calculate(User $user): array
    {
        $today = CarbonImmutable::today();
        $tomorrow = $today->addDay();

        $expressions = ['COUNT(*) AS total'];
        $bindings = [];

        $sum = function (
            string $alias,
            string $condition,
            array $parameters = []
        ) use (&$expressions, &$bindings): void {
            $expressions[] = sprintf(
                'COALESCE(SUM(CASE WHEN %s THEN 1 ELSE 0 END), 0) AS %s',
                $condition,
                $alias
            );

            array_push($bindings, ...$parameters);
        };

        $sum(
            'open_count',
            'status IN (?, ?, ?)',
            InquiryOptions::OPEN_STATUSES
        );

        $sum('pending_count', 'status = ?', ['pending']);

        $sum(
            'closed_count',
            'status IN (?, ?)',
            InquiryOptions::CLOSED_STATUSES
        );

        $sum('won_count', 'status = ?', ['won']);
        $sum('lost_count', 'status = ?', ['lost']);

        $sum(
            'new_today_count',
            'created_at >= ? AND created_at < ?',
            [$today, $tomorrow]
        );

        $sum(
            'my_open_assigned_count',
            'assigned_to = ? AND status IN (?, ?, ?)',
            [$user->id, ...InquiryOptions::OPEN_STATUSES]
        );

        foreach (InquiryOptions::STATUSES as $index => $status) {
            $sum('status_'.$index, 'status = ?', [$status]);
        }

        foreach (InquiryOptions::SOURCES as $index => $source) {
            $sum('source_'.$index, 'source = ?', [$source]);
        }

        $days = [];

        for ($index = 0; $index < 30; $index++) {
            $day = $today->subDays(29 - $index);
            $days[$index] = $day;

            $sum(
                'day_'.$index,
                'created_at >= ? AND created_at < ?',
                [$day, $day->addDay()]
            );
        }

        // Counts, status/source groups, and daily buckets use one aggregate
        // query. Agent visibility is applied before aggregation.
        $row = Inquiry::query()
            ->visibleTo($user)
            ->selectRaw(implode(', ', $expressions), $bindings)
            ->toBase()
            ->first();

        $closed = (int) $row->closed_count;
        $won = (int) $row->won_count;

        $byStatus = [];

        foreach (InquiryOptions::STATUSES as $index => $status) {
            $byStatus[] = [
                'status' => $status,
                'count' => (int) $row->{'status_'.$index},
            ];
        }

        $bySource = [];

        foreach (InquiryOptions::SOURCES as $index => $source) {
            $bySource[] = [
                'source' => $source,
                'count' => (int) $row->{'source_'.$index},
            ];
        }

        $perDay = [];

        foreach ($days as $index => $day) {
            $perDay[] = [
                'date' => $day->format('Y-m-d'),
                'count' => (int) $row->{'day_'.$index},
            ];
        }

        return [
            'total' => (int) $row->total,
            'open' => (int) $row->open_count,
            'pending' => (int) $row->pending_count,
            'closed' => $closed,
            'won' => $won,
            'lost' => (int) $row->lost_count,
            'new_today' => (int) $row->new_today_count,
            'conversion_rate' => $closed === 0
                ? 0.0
                : round(($won / $closed) * 100, 2),
            'my_open_assigned' => (int) $row->my_open_assigned_count,
            'by_status' => $byStatus,
            'by_source' => $bySource,
            'per_day' => $perDay,
        ];
    }
}
