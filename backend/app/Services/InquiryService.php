<?php

namespace App\Services;

use App\Events\InquiryCreated;
use App\Mail\StaffReply;
use App\Models\Inquiry;
use App\Models\InquiryMessage;
use App\Models\Note;
use App\Models\Reminder;
use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Mail;
use Illuminate\Validation\ValidationException;
use Throwable;

class InquiryService
{
    private const PUBLIC_FIELDS = [
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
    ];

    private const EDITABLE_FIELDS = [
        'name',
        'email',
        'phone',
        'company',
        'subject',
        'source',
        'utm_source',
        'utm_medium',
        'utm_campaign',
        'priority',
    ];

    public function __construct(
        private readonly AssignmentService $assignment,
        private readonly AttachmentService $attachments,
        private readonly ActivityLogger $activityLogger
    ) {
    }

    public function paginate(
        array $filters,
        User $actor
    ): LengthAwarePaginator {
        Gate::forUser($actor)->authorize('viewAny', Inquiry::class);

        return Inquiry::query()
            ->select(Inquiry::LIST_COLUMNS)
            ->visibleTo($actor)
            ->filter($filters)
            ->sorted($filters)
            ->with('assignee:id,name,email,role,is_active')
            ->withCount([
                'messages',
                'notes',
                'attachments',
                'reminders as incomplete_reminders_count' =>
                    fn ($query) => $query->where('is_completed', false),
            ])
            ->paginate(
                min(
                    (int) ($filters['per_page']
                        ?? config('inquiry.pagination.default', 15)),
                    (int) config('inquiry.pagination.maximum', 100)
                )
            )
            ->withQueryString();
    }

    public function detail(Inquiry $inquiry, User $actor): Inquiry
    {
        Gate::forUser($actor)->authorize('view', $inquiry);

        return $inquiry->load([
            'assignee:id,name,email,role,is_active',
            'messages.user:id,name',
            'notes.user:id,name',
            'reminders.user:id,name',
            'attachments.uploader:id,name',
            'activityLogs.user:id,name',
        ]);
    }

    /**
     * @param array<int, UploadedFile> $files
     */
    public function createPublic(
        array $data,
        array $files,
        ?string $ipAddress
    ): Inquiry {
        $storedPaths = [];

        try {
            return DB::transaction(function () use (
                $data,
                $files,
                $ipAddress,
                &$storedPaths
            ): Inquiry {
                $agent = $this->assignment->nextAgent();

                $inquiry = Inquiry::query()->create([
                    ...Arr::only($data, self::PUBLIC_FIELDS),
                    'source' => $data['source'] ?? 'website',
                    'status' => 'new',
                    'priority' => 'medium',
                    'assigned_to' => $agent?->id,
                    'ip_address' => $ipAddress,
                ]);

                $inquiry->messages()->create([
                    'sender_type' => 'customer',
                    'user_id' => null,
                    'body' => $inquiry->message,
                ]);

                if ($files !== []) {
                    $saved = $this->attachments->store($inquiry, $files);
                    $storedPaths = $saved->pluck('stored_path')->all();
                }

                if ($agent !== null) {
                    $this->activityLogger->log(
                        action: 'assigned',
                        description: 'Inquiry automatically assigned to '
                            .$agent->name.'.',
                        inquiry: $inquiry,
                        oldValues: ['assigned_to' => null],
                        newValues: ['assigned_to' => $agent->id]
                    );
                }

                // The synchronous audit listener participates in this
                // transaction. Notification listeners run after commit.
                InquiryCreated::dispatch($inquiry);

                return $inquiry;
            });
        } catch (Throwable $exception) {
            $this->attachments->cleanup($storedPaths);

            throw $exception;
        }
    }

    public function update(
        Inquiry $inquiry,
        array $data,
        User $actor
    ): Inquiry {
        return DB::transaction(function () use (
            $inquiry,
            $data,
            $actor
        ): Inquiry {
            $inquiry = $this->lockInquiry($inquiry);

            Gate::forUser($actor)->authorize('update', $inquiry);

            $inquiry->fill(Arr::only($data, self::EDITABLE_FIELDS));
            $changes = $inquiry->getDirty();

            if ($changes !== []) {
                $before = Arr::only(
                    $inquiry->getOriginal(),
                    array_keys($changes)
                );

                $inquiry->save();

                $this->activityLogger->log(
                    action: 'updated',
                    description: 'Inquiry details updated.',
                    inquiry: $inquiry,
                    actor: $actor,
                    oldValues: $before,
                    newValues: $changes
                );
            }

            return $inquiry->load('assignee:id,name,email,role,is_active');
        });
    }

    public function changeStatus(
        Inquiry $inquiry,
        string $status,
        ?string $note,
        User $actor
    ): Inquiry {
        return DB::transaction(function () use (
            $inquiry,
            $status,
            $note,
            $actor
        ): Inquiry {
            $inquiry = $this->lockInquiry($inquiry);

            Gate::forUser($actor)->authorize('update', $inquiry);

            $previousStatus = $inquiry->status;
            $previousClosedAt = $inquiry->closed_at?->toIso8601String();

            if ($previousStatus !== $status) {
                $inquiry->status = $status;
                $inquiry->save();

                $this->activityLogger->log(
                    action: 'status_changed',
                    description: 'Status changed from '.$previousStatus
                        .' to '.$status.'.',
                    inquiry: $inquiry,
                    actor: $actor,
                    oldValues: [
                        'status' => $previousStatus,
                        'closed_at' => $previousClosedAt,
                    ],
                    newValues: [
                        'status' => $status,
                        'closed_at' => $inquiry->closed_at?->toIso8601String(),
                    ]
                );
            }

            if ($note !== null && trim($note) !== '') {
                $this->addNote($inquiry, ['body' => $note], $actor);
            }

            return $inquiry->load('assignee:id,name,email,role,is_active');
        });
    }

    public function assign(
        Inquiry $inquiry,
        ?int $userId,
        User $actor
    ): Inquiry {
        return DB::transaction(function () use (
            $inquiry,
            $userId,
            $actor
        ): Inquiry {
            $inquiry = $this->lockInquiry($inquiry);

            Gate::forUser($actor)->authorize('assign', $inquiry);

            $assignee = $userId === null
                ? null
                : User::query()->assignable()->find($userId);

            if ($userId !== null && $assignee === null) {
                throw ValidationException::withMessages([
                    'assigned_to' => [
                        'Select an active agent or leave the inquiry unassigned.',
                    ],
                ]);
            }

            $previousId = $inquiry->assigned_to;

            if ($previousId !== $userId) {
                $inquiry->assigned_to = $userId;
                $inquiry->save();

                $this->activityLogger->log(
                    action: 'assigned',
                    description: $assignee === null
                        ? 'Inquiry assignment removed.'
                        : 'Inquiry assigned to '.$assignee->name.'.',
                    inquiry: $inquiry,
                    actor: $actor,
                    oldValues: ['assigned_to' => $previousId],
                    newValues: ['assigned_to' => $userId]
                );
            }

            return $inquiry->load('assignee:id,name,email,role,is_active');
        });
    }

    public function delete(Inquiry $inquiry, User $actor): void
    {
        DB::transaction(function () use ($inquiry, $actor): void {
            $inquiry = $this->lockInquiry($inquiry);

            Gate::forUser($actor)->authorize('delete', $inquiry);

            $this->activityLogger->log(
                action: 'deleted',
                description: 'Inquiry '.$inquiry->reference_no
                    .' moved to the deleted records.',
                inquiry: $inquiry,
                actor: $actor,
                oldValues: [
                    'reference_no' => $inquiry->reference_no,
                    'status' => $inquiry->status,
                    'assigned_to' => $inquiry->assigned_to,
                    'deleted_at' => null,
                ],
                newValues: [
                    'deleted_at' => now()->toIso8601String(),
                ]
            );

            // Keep related records and files for the soft-deleted inquiry.
            $inquiry->delete();
        });
    }

    public function addMessage(
        Inquiry $inquiry,
        array $data,
        User $actor
    ): InquiryMessage {
        return DB::transaction(function () use (
            $inquiry,
            $data,
            $actor
        ): InquiryMessage {
            $inquiry = $this->lockInquiry($inquiry);

            Gate::forUser($actor)->authorize('update', $inquiry);

            $message = $inquiry->messages()->create([
                'sender_type' => 'staff',
                'user_id' => $actor->id,
                'body' => $data['body'],
            ]);

            $this->activityLogger->log(
                action: 'message_added',
                description: 'Staff reply added to the conversation.',
                inquiry: $inquiry,
                actor: $actor,
                newValues: [
                    'message_id' => $message->id,
                    'body' => $message->body,
                ]
            );

            Mail::to($inquiry->email)->queue(
                (new StaffReply($inquiry, $message))->afterCommit()
            );

            return $message->load('user:id,name');
        });
    }

    public function addNote(
        Inquiry $inquiry,
        array $data,
        User $actor
    ): Note {
        return DB::transaction(function () use (
            $inquiry,
            $data,
            $actor
        ): Note {
            $inquiry = $this->lockInquiry($inquiry);

            Gate::forUser($actor)->authorize('update', $inquiry);

            $note = $inquiry->notes()->create([
                'user_id' => $actor->id,
                'body' => $data['body'],
                'is_internal' => true,
            ]);

            $this->activityLogger->log(
                action: 'note_added',
                description: 'Internal note added.',
                inquiry: $inquiry,
                actor: $actor,
                newValues: [
                    'note_id' => $note->id,
                    'body' => $note->body,
                    'is_internal' => true,
                ]
            );

            return $note->load('user:id,name');
        });
    }

    public function updateNote(
        Note $note,
        array $data,
        User $actor
    ): Note {
        return DB::transaction(function () use (
            $note,
            $data,
            $actor
        ): Note {
            $note = Note::query()
                ->lockForUpdate()
                ->findOrFail($note->id);

            $note->load('inquiry');

            abort_if($note->inquiry === null, 404);
            Gate::forUser($actor)->authorize('update', $note);

            $before = $note->body;
            $note->body = $data['body'];

            if ($note->isDirty('body')) {
                $note->save();

                $this->activityLogger->log(
                    action: 'note_updated',
                    description: 'Internal note updated.',
                    inquiry: $note->inquiry,
                    actor: $actor,
                    oldValues: [
                        'note_id' => $note->id,
                        'body' => $before,
                    ],
                    newValues: [
                        'note_id' => $note->id,
                        'body' => $note->body,
                    ]
                );
            }

            return $note->load('user:id,name');
        });
    }

    public function deleteNote(Note $note, User $actor): void
    {
        DB::transaction(function () use ($note, $actor): void {
            $note = Note::query()
                ->lockForUpdate()
                ->findOrFail($note->id);

            $note->load('inquiry');

            abort_if($note->inquiry === null, 404);
            Gate::forUser($actor)->authorize('delete', $note);

            $this->activityLogger->log(
                action: 'note_deleted',
                description: 'Internal note deleted.',
                inquiry: $note->inquiry,
                actor: $actor,
                oldValues: [
                    'note_id' => $note->id,
                    'body' => $note->body,
                ]
            );

            $note->delete();
        });
    }

    public function addReminder(
        Inquiry $inquiry,
        array $data,
        User $actor
    ): Reminder {
        return DB::transaction(function () use (
            $inquiry,
            $data,
            $actor
        ): Reminder {
            $inquiry = $this->lockInquiry($inquiry);

            Gate::forUser($actor)->authorize('update', $inquiry);

            $reminder = $inquiry->reminders()->create([
                'user_id' => $actor->id,
                'title' => $data['title'],
                'remind_at' => $data['remind_at'],
                'is_completed' => false,
                'notified_at' => null,
            ]);

            $this->activityLogger->log(
                action: 'reminder_added',
                description: 'Follow-up reminder created.',
                inquiry: $inquiry,
                actor: $actor,
                newValues: [
                    'reminder_id' => $reminder->id,
                    'title' => $reminder->title,
                    'remind_at' => $reminder->remind_at->toIso8601String(),
                ]
            );

            return $reminder->load([
                'user:id,name',
                'inquiry:id,reference_no,subject,assigned_to',
            ]);
        });
    }

    public function completeReminder(
        Reminder $reminder,
        User $actor
    ): Reminder {
        return DB::transaction(function () use (
            $reminder,
            $actor
        ): Reminder {
            $reminder = Reminder::query()
                ->lockForUpdate()
                ->findOrFail($reminder->id);

            $reminder->load('inquiry');

            abort_if($reminder->inquiry === null, 404);
            Gate::forUser($actor)->authorize('update', $reminder);

            if (! $reminder->is_completed) {
                $reminder->is_completed = true;
                $reminder->save();

                $this->activityLogger->log(
                    action: 'reminder_completed',
                    description: 'Follow-up reminder marked complete.',
                    inquiry: $reminder->inquiry,
                    actor: $actor,
                    oldValues: [
                        'is_completed' => false,
                    ],
                    newValues: [
                        'reminder_id' => $reminder->id,
                        'is_completed' => true,
                    ]
                );
            }

            return $reminder->load('user:id,name');
        });
    }

    public function deleteReminder(
        Reminder $reminder,
        User $actor
    ): void {
        DB::transaction(function () use ($reminder, $actor): void {
            $reminder = Reminder::query()
                ->lockForUpdate()
                ->findOrFail($reminder->id);

            $reminder->load('inquiry');

            abort_if($reminder->inquiry === null, 404);
            Gate::forUser($actor)->authorize('delete', $reminder);

            $this->activityLogger->log(
                action: 'reminder_deleted',
                description: 'Follow-up reminder deleted.',
                inquiry: $reminder->inquiry,
                actor: $actor,
                oldValues: [
                    'reminder_id' => $reminder->id,
                    'title' => $reminder->title,
                    'remind_at' => $reminder->remind_at->toIso8601String(),
                    'is_completed' => $reminder->is_completed,
                ]
            );

            $reminder->delete();
        });
    }

    private function lockInquiry(Inquiry $inquiry): Inquiry
    {
        return Inquiry::query()
            ->lockForUpdate()
            ->findOrFail($inquiry->id);
    }
}
