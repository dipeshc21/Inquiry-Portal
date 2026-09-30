<?php

namespace App\Models;

use App\Support\InquiryOptions;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Inquiry extends Model
{
    use HasFactory;
    use SoftDeletes;

    public const LIST_COLUMNS = [
        'inquiries.id',
        'inquiries.reference_no',
        'inquiries.name',
        'inquiries.email',
        'inquiries.phone',
        'inquiries.company',
        'inquiries.subject',
        'inquiries.source',
        'inquiries.status',
        'inquiries.priority',
        'inquiries.assigned_to',
        'inquiries.closed_at',
        'inquiries.created_at',
        'inquiries.updated_at',
    ];

    protected $fillable = [
        'name',
        'email',
        'phone',
        'company',
        'subject',
        'message',
        'source',
        'utm_source',
        'utm_medium',
        'utm_campaign',
        'status',
        'priority',
        'assigned_to',
        'ip_address',
    ];

    protected function casts(): array
    {
        return [
            'assigned_to' => 'integer',
            'closed_at' => 'immutable_datetime',
            'created_at' => 'immutable_datetime',
            'updated_at' => 'immutable_datetime',
            'deleted_at' => 'immutable_datetime',
        ];
    }

    public function assignee(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_to');
    }

    public function messages(): HasMany
    {
        return $this->hasMany(InquiryMessage::class)
            ->orderBy('created_at')
            ->orderBy('id');
    }

    public function notes(): HasMany
    {
        return $this->hasMany(Note::class)
            ->orderByDesc('created_at')
            ->orderByDesc('id');
    }

    public function reminders(): HasMany
    {
        return $this->hasMany(Reminder::class)
            ->orderBy('remind_at')
            ->orderBy('id');
    }

    public function attachments(): HasMany
    {
        return $this->hasMany(Attachment::class)
            ->orderByDesc('created_at')
            ->orderByDesc('id');
    }

    public function activityLogs(): HasMany
    {
        return $this->hasMany(ActivityLog::class)
            ->orderByDesc('created_at')
            ->orderByDesc('id');
    }

    public function scopeVisibleTo(Builder $query, User $user): Builder
    {
        if (! $user->is_active) {
            return $query->whereRaw('1 = 0');
        }

        if ($user->isAgent()) {
            return $query->where('inquiries.assigned_to', $user->id);
        }

        if ($user->canManageInquiries()) {
            return $query;
        }

        return $query->whereRaw('1 = 0');
    }

    public function scopeOpen(Builder $query): Builder
    {
        return $query->whereIn(
            'inquiries.status',
            InquiryOptions::OPEN_STATUSES
        );
    }

    public function scopePending(Builder $query): Builder
    {
        return $query->where('inquiries.status', 'pending');
    }

    public function scopeClosed(Builder $query): Builder
    {
        return $query->whereIn(
            'inquiries.status',
            InquiryOptions::CLOSED_STATUSES
        );
    }

    public function scopeUnresolved(Builder $query): Builder
    {
        return $query->whereNotIn(
            'inquiries.status',
            InquiryOptions::CLOSED_STATUSES
        );
    }

    public function scopeSearch(
        Builder $query,
        ?string $search
    ): Builder {
        $search = trim((string) $search);

        if ($search === '') {
            return $query;
        }

        // Escape LIKE metacharacters so user input is a literal substring.
        // An explicit escape character works consistently in MySQL and SQLite.
        $escaped = str_replace(
            ['!', '%', '_'],
            ['!!', '!%', '!_'],
            $search
        );

        $pattern = '%'.$escaped.'%';

        return $query->where(function (Builder $nested) use ($pattern): void {
            foreach ([
                'name',
                'email',
                'phone',
                'subject',
                'reference_no',
                'message',
            ] as $index => $column) {
                $sql = "inquiries.{$column} LIKE ? ESCAPE '!'";

                if ($index === 0) {
                    $nested->whereRaw($sql, [$pattern]);
                } else {
                    $nested->orWhereRaw($sql, [$pattern]);
                }
            }
        });
    }

    public function scopeFilter(
        Builder $query,
        array $filters
    ): Builder {
        $query->search($filters['q'] ?? null);

        foreach (['status', 'source'] as $column) {
            if (! empty($filters[$column])) {
                $values = is_array($filters[$column])
                    ? $filters[$column]
                    : [$filters[$column]];

                $query->whereIn('inquiries.'.$column, $values);
            }
        }

        if (! empty($filters['priority'])) {
            $query->where('inquiries.priority', $filters['priority']);
        }

        $unassigned = filter_var(
            $filters['unassigned'] ?? false,
            FILTER_VALIDATE_BOOLEAN
        );

        if ($unassigned) {
            $query->whereNull('inquiries.assigned_to');
        } elseif (
            isset($filters['assigned_to'])
            && $filters['assigned_to'] !== ''
        ) {
            $query->where('inquiries.assigned_to', $filters['assigned_to']);
        }

        if (! empty($filters['date_from'])) {
            $query->where(
                'inquiries.created_at',
                '>=',
                CarbonImmutable::parse($filters['date_from'])->startOfDay()
            );
        }

        if (! empty($filters['date_to'])) {
            // A half-open range preserves timestamp precision and index use.
            $query->where(
                'inquiries.created_at',
                '<',
                CarbonImmutable::parse($filters['date_to'])
                    ->startOfDay()
                    ->addDay()
            );
        }

        if (! empty($filters['ids'])) {
            $query->whereIn('inquiries.id', $filters['ids']);
        }

        return $query;
    }

    public function scopeSorted(
        Builder $query,
        array $filters = []
    ): Builder {
        $column = $filters['sort_by'] ?? 'created_at';

        if (! in_array($column, InquiryOptions::SORT_COLUMNS, true)) {
            $column = 'created_at';
        }

        $direction = strtolower($filters['sort_dir'] ?? 'desc');
        $direction = $direction === 'asc' ? 'asc' : 'desc';

        if ($column === 'priority') {
            $query->orderByRaw(
                "CASE inquiries.priority
                    WHEN 'low' THEN 1
                    WHEN 'medium' THEN 2
                    WHEN 'high' THEN 3
                END {$direction}"
            );
        } elseif ($column === 'status') {
            $query->orderByRaw(
                "CASE inquiries.status
                    WHEN 'new' THEN 1
                    WHEN 'contacted' THEN 2
                    WHEN 'qualified' THEN 3
                    WHEN 'pending' THEN 4
                    WHEN 'won' THEN 5
                    WHEN 'lost' THEN 6
                END {$direction}"
            );
        } else {
            $query->orderBy('inquiries.'.$column, $direction);
        }

        return $query->orderBy('inquiries.id', $direction);
    }
}
