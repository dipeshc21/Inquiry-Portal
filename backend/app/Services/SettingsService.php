<?php

namespace App\Services;

use App\Models\Setting;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

class SettingsService
{
    public function __construct(
        private readonly ActivityLogger $activityLogger
    ) {
    }

    public function assignment(): array
    {
        $settings = Setting::query()
            ->whereIn('key', [
                'inquiry.auto_assign',
                'inquiry.strategy',
            ])
            ->get()
            ->keyBy('key');

        return [
            'auto_assign' => (bool) (
                $settings->get('inquiry.auto_assign')?->value
                ?? config('inquiry.auto_assign', true)
            ),
            'strategy' => (string) (
                $settings->get('inquiry.strategy')?->value
                ?? config('inquiry.strategy', 'round_robin')
            ),
        ];
    }

    public function updateAssignment(array $data, User $actor): array
    {
        Gate::forUser($actor)->authorize('manage-settings');

        return DB::transaction(function () use ($data, $actor): array {
            // Settings updates and assignment decisions use the same lock.
            DB::table('assignment_locks')
                ->where('id', 1)
                ->lockForUpdate()
                ->first();

            $before = $this->assignment();

            Setting::query()->updateOrCreate(
                ['key' => 'inquiry.auto_assign'],
                ['value' => (bool) $data['auto_assign']]
            );

            Setting::query()->updateOrCreate(
                ['key' => 'inquiry.strategy'],
                ['value' => $data['strategy']]
            );

            $after = $this->assignment();

            if ($before !== $after) {
                $this->activityLogger->log(
                    action: 'settings_updated',
                    description: 'Automatic assignment settings updated.',
                    actor: $actor,
                    oldValues: $before,
                    newValues: $after
                );
            }

            return $after;
        });
    }
}
