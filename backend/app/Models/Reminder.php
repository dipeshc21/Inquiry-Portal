<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Reminder extends Model
{
    protected $fillable = [
        'inquiry_id',
        'user_id',
        'title',
        'remind_at',
        'is_completed',
        'notified_at',
    ];

    protected function casts(): array
    {
        return [
            'remind_at' => 'immutable_datetime',
            'is_completed' => 'boolean',
            'notified_at' => 'immutable_datetime',
        ];
    }

    public function inquiry(): BelongsTo
    {
        return $this->belongsTo(Inquiry::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function scopeForUser(Builder $query, User $user): Builder
    {
        return $query->where('user_id', $user->id);
    }

    public function scopeAccessibleTo(
        Builder $query,
        User $user
    ): Builder {
        return $query->whereHas(
            'inquiry',
            fn (Builder $inquiries): Builder => $inquiries->visibleTo($user)
        );
    }

    public function scopeIncomplete(Builder $query): Builder
    {
        return $query->where('is_completed', false);
    }

    public function scopeDue(Builder $query): Builder
    {
        return $query
            ->incomplete()
            ->whereNull('notified_at')
            ->where('remind_at', '<=', now())
            ->whereHas('inquiry')
            ->whereHas(
                'user',
                fn (Builder $users): Builder => $users->active()
            );
    }

    public function scopeUpcoming(
        Builder $query,
        ?int $days = null
    ): Builder {
        $days ??= (int) config('inquiry.reminder_lookahead_days', 30);

        return $query
            ->incomplete()
            ->where('remind_at', '>=', now())
            ->where('remind_at', '<=', now()->addDays($days));
    }

    public function scopeOverdue(Builder $query): Builder
    {
        return $query
            ->incomplete()
            ->where('remind_at', '<', now());
    }
}
