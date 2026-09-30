<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use LogicException;

class AssignmentService
{
    public function __construct(
        private readonly SettingsService $settings
    ) {
    }

    public function nextAgent(): ?User
    {
        return DB::transaction(function (): ?User {
            // A persistent mutex row prevents concurrent requests from
            // selecting the same round-robin position.
            DB::table('assignment_locks')
                ->where('id', 1)
                ->lockForUpdate()
                ->first();

            $settings = $this->settings->assignment();

            if (! $settings['auto_assign']) {
                return null;
            }

            $query = User::query()->assignable();

            if ($settings['strategy'] === 'least_loaded') {
                $query->withCount([
                    'assignedInquiries as unresolved_count' =>
                        fn (Builder $inquiries): Builder =>
                            $inquiries->unresolved(),
                ])->orderBy('unresolved_count');
            } elseif ($settings['strategy'] !== 'round_robin') {
                throw new LogicException(
                    'Unsupported inquiry assignment strategy.'
                );
            }

            $agent = $query
                ->orderByRaw(
                    'CASE WHEN last_assigned_at IS NULL THEN 0 ELSE 1 END'
                )
                ->orderBy('last_assigned_at')
                ->orderBy('id')
                ->lockForUpdate()
                ->first();

            if ($agent === null) {
                return null;
            }

            // MySQL timestamp columns here have second precision. Ensure that
            // assignments in the same second still have a strict order.
            $latest = User::query()
                ->whereNotNull('last_assigned_at')
                ->orderByDesc('last_assigned_at')
                ->value('last_assigned_at');

            $assignedAt = now();

            if ($latest !== null) {
                $previous = \Carbon\CarbonImmutable::parse($latest);

                if ($assignedAt->lessThanOrEqualTo($previous)) {
                    $assignedAt = $previous->addSecond();
                }
            }

            $agent->forceFill([
                'last_assigned_at' => $assignedAt,
            ])->save();

            return $agent;
        });
    }
}
