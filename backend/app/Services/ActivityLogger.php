<?php

namespace App\Services;

use App\Models\ActivityLog;
use App\Models\Inquiry;
use App\Models\User;
use Illuminate\Support\Arr;

class ActivityLogger
{
    private const SENSITIVE_KEYS = [
        'password',
        'password_confirmation',
        'current_password',
        'remember_token',
        'token',
        'plain_text_token',
        'stored_path',
    ];

    public function log(
        string $action,
        string $description,
        ?Inquiry $inquiry = null,
        ?User $actor = null,
        ?array $oldValues = null,
        ?array $newValues = null
    ): ActivityLog {
        return ActivityLog::query()->create([
            'inquiry_id' => $inquiry?->id,
            'user_id' => $actor?->id,
            'action' => $action,
            'description' => $description,
            'old_values' => $this->sanitize($oldValues),
            'new_values' => $this->sanitize($newValues),
        ]);
    }

    private function sanitize(?array $values): ?array
    {
        if ($values === null) {
            return null;
        }

        $values = Arr::except($values, self::SENSITIVE_KEYS);

        foreach ($values as $key => $value) {
            if (is_array($value)) {
                $values[$key] = $this->sanitize($value);
            }
        }

        return $values;
    }
}
