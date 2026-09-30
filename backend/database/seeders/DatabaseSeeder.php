<?php

namespace Database\Seeders;

use App\Models\ActivityLog;
use App\Models\Inquiry;
use App\Models\InquiryMessage;
use App\Models\Note;
use App\Models\Reminder;
use App\Models\Team;
use App\Models\User;
use App\Support\InquiryOptions;
use Carbon\CarbonImmutable;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        DB::transaction(function (): void {
            $sales = Team::query()->firstOrCreate([
                'name' => 'Sales',
            ]);

            $success = Team::query()->firstOrCreate([
                'name' => 'Customer Success',
            ]);

            $admin = $this->demoUser(
                'admin@example.com',
                'Alex Morgan',
                'admin',
                $sales->id
            );

            $manager = $this->demoUser(
                'manager@example.com',
                'Taylor Brooks',
                'manager',
                $sales->id
            );

            $agents = collect([
                $this->demoUser(
                    'agent1@example.com',
                    'Jordan Patel',
                    'agent',
                    $sales->id
                ),
                $this->demoUser(
                    'agent2@example.com',
                    'Sam Rivera',
                    'agent',
                    $sales->id
                ),
                $this->demoUser(
                    'agent3@example.com',
                    'Casey Chen',
                    'agent',
                    $success->id
                ),
            ]);

            // Re-running the seeder preserves existing inquiries and passwords.
            if (Inquiry::withTrashed()->exists()) {
                return;
            }

            $now = CarbonImmutable::now();
            $statuses = InquiryOptions::STATUSES;
            $sources = InquiryOptions::SOURCES;
            $priorities = InquiryOptions::PRIORITIES;

            for ($index = 0; $index < 60; $index++) {
                $createdAt = $index < 6
                    ? $now->subMinutes(($index + 1) * 5)
                    : $now
                        ->subDays(1 + (($index * 7) % 89))
                        ->subMinutes($index * 3);

                $status = $statuses[$index % count($statuses)];
                $source = $sources[intdiv($index, 2) % count($sources)];

                $assignee = $index % 10 === 0
                    ? null
                    : $agents[$index % $agents->count()];

                $closedAt = InquiryOptions::isClosed($status)
                    ? $createdAt->addDays(4)->min($now)
                    : null;

                $inquiry = Inquiry::factory()->create([
                    'status' => $status,
                    'source' => $source,
                    'priority' => $priorities[
                        intdiv($index, 3) % count($priorities)
                    ],
                    'assigned_to' => $assignee?->id,
                    'utm_source' => $source === 'campaign'
                        ? 'google'
                        : null,
                    'utm_medium' => $source === 'campaign'
                        ? 'cpc'
                        : null,
                    'utm_campaign' => $source === 'campaign'
                        ? 'enterprise-demo'
                        : null,
                    'closed_at' => $closedAt,
                    'created_at' => $createdAt,
                    'updated_at' => $closedAt ?? $createdAt,
                ]);

                InquiryMessage::query()->create([
                    'inquiry_id' => $inquiry->id,
                    'sender_type' => 'customer',
                    'user_id' => null,
                    'body' => $inquiry->message,
                    'created_at' => $createdAt,
                    'updated_at' => $createdAt,
                ]);

                $this->activity(
                    $inquiry,
                    null,
                    'created',
                    'Inquiry received through the '.$source.' channel.',
                    null,
                    [
                        'reference_no' => $inquiry->reference_no,
                        'status' => 'new',
                        'source' => $source,
                    ],
                    $createdAt
                );

                if ($assignee !== null) {
                    $this->activity(
                        $inquiry,
                        $manager,
                        'assigned',
                        'Inquiry assigned to '.$assignee->name.'.',
                        ['assigned_to' => null],
                        ['assigned_to' => $assignee->id],
                        $createdAt->addMinute()->min($now)
                    );

                    if (
                        $assignee->last_assigned_at === null
                        || $createdAt->greaterThan($assignee->last_assigned_at)
                    ) {
                        $assignee->forceFill([
                            'last_assigned_at' => $createdAt,
                        ])->save();
                    }
                }

                $author = $assignee ?? $manager;
                $replyAt = $createdAt->addMinutes(2)->min($now);

                if ($status !== 'new') {
                    $reply = InquiryMessage::query()->create([
                        'inquiry_id' => $inquiry->id,
                        'sender_type' => 'staff',
                        'user_id' => $author->id,
                        'body' => 'Thank you for contacting us. I have reviewed '
                            .'your requirements and will help you evaluate the '
                            .'available options. Could you share your preferred '
                            .'time for a short introductory call?',
                        'created_at' => $replyAt,
                        'updated_at' => $replyAt,
                    ]);

                    $this->activity(
                        $inquiry,
                        $author,
                        'message_added',
                        'Staff reply added to the conversation.',
                        null,
                        ['message_id' => $reply->id],
                        $replyAt
                    );

                    $this->activity(
                        $inquiry,
                        $author,
                        'status_changed',
                        'Status changed from new to '.$status.'.',
                        ['status' => 'new'],
                        ['status' => $status],
                        $closedAt ?? $replyAt
                    );
                }

                if ($index % 3 === 0) {
                    $customerReplyAt = $replyAt->addMinute()->min($now);

                    InquiryMessage::query()->create([
                        'inquiry_id' => $inquiry->id,
                        'sender_type' => 'customer',
                        'user_id' => null,
                        'body' => 'Thank you for the quick response. Our team '
                            .'is available this week. Please send the product '
                            .'overview before the call so we can prepare.',
                        'created_at' => $customerReplyAt,
                        'updated_at' => $customerReplyAt,
                    ]);
                }

                if ($index % 2 === 0) {
                    $noteAt = $createdAt->addMinutes(3)->min($now);

                    $note = Note::query()->create([
                        'inquiry_id' => $inquiry->id,
                        'user_id' => $author->id,
                        'body' => fake()->randomElement([
                            'Customer is comparing three providers. Emphasize '
                                .'implementation support and reporting.',
                            'Budget approval is expected this month. Follow up '
                                .'after the stakeholder meeting.',
                            'Technical team needs an integration walkthrough '
                                .'before commercial discussions.',
                            'Potential multi-team rollout. Prepare a phased '
                                .'implementation proposal.',
                            'Customer prefers email contact in the morning. '
                                .'Confirm requirements before quoting.',
                        ]),
                        'is_internal' => true,
                        'created_at' => $noteAt,
                        'updated_at' => $noteAt,
                    ]);

                    $this->activity(
                        $inquiry,
                        $author,
                        'note_added',
                        'Internal note added.',
                        null,
                        [
                            'note_id' => $note->id,
                            'body' => $note->body,
                            'is_internal' => true,
                        ],
                        $noteAt
                    );
                }

                $completed = InquiryOptions::isClosed($status);

                if ($completed) {
                    $remindAt = $closedAt ?? $now;
                } elseif ($index % 3 === 0) {
                    $remindAt = $now->subHours(1 + ($index % 24));
                } else {
                    $remindAt = $now
                        ->addDays(1 + ($index % 10))
                        ->setTime(10 + ($index % 6), 0);
                }

                $reminderCreatedAt = $createdAt
                    ->addMinutes(4)
                    ->min($now);

                $reminder = Reminder::query()->create([
                    'inquiry_id' => $inquiry->id,
                    'user_id' => $author->id,
                    'title' => $completed
                        ? 'Confirm final outcome with customer'
                        : fake()->randomElement([
                            'Follow up on proposal',
                            'Arrange product demonstration',
                            'Confirm budget and timeline',
                            'Send integration documentation',
                            'Check stakeholder feedback',
                        ]),
                    'remind_at' => $remindAt,
                    'is_completed' => $completed,
                    'notified_at' => $completed ? $remindAt : null,
                    'created_at' => $reminderCreatedAt,
                    'updated_at' => $completed
                        ? $now
                        : $reminderCreatedAt,
                ]);

                $this->activity(
                    $inquiry,
                    $author,
                    'reminder_added',
                    'Follow-up reminder created.',
                    null,
                    [
                        'reminder_id' => $reminder->id,
                        'title' => $reminder->title,
                        'remind_at' => $reminder->remind_at->toIso8601String(),
                    ],
                    $reminderCreatedAt
                );

                if ($completed) {
                    $this->activity(
                        $inquiry,
                        $author,
                        'reminder_completed',
                        'Follow-up reminder marked complete.',
                        ['is_completed' => false],
                        [
                            'reminder_id' => $reminder->id,
                            'is_completed' => true,
                        ],
                        $now
                    );
                }
            }

            ActivityLog::query()->create([
                'inquiry_id' => null,
                'user_id' => $admin->id,
                'action' => 'demo_seeded',
                'description' => 'Demo workspace initialized with 60 inquiries.',
                'old_values' => null,
                'new_values' => ['inquiry_count' => 60],
            ]);
        });
    }

    private function demoUser(
        string $email,
        string $name,
        string $role,
        int $teamId
    ): User {
        return User::query()->firstOrCreate(
            ['email' => $email],
            [
                'name' => $name,
                'password' => Hash::make('password'),
                'role' => $role,
                'team_id' => $teamId,
                'is_active' => true,
            ]
        );
    }

    private function activity(
        Inquiry $inquiry,
        ?User $actor,
        string $action,
        string $description,
        ?array $oldValues,
        ?array $newValues,
        CarbonImmutable $at
    ): void {
        ActivityLog::query()->create([
            'inquiry_id' => $inquiry->id,
            'user_id' => $actor?->id,
            'action' => $action,
            'description' => $description,
            'old_values' => $oldValues,
            'new_values' => $newValues,
            'created_at' => $at,
            'updated_at' => $at,
        ]);
    }
}
