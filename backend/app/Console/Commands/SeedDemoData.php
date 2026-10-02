<?php

namespace App\Console\Commands;

use App\Models\ActivityLog;
use App\Models\Inquiry;
use App\Models\InquiryMessage;
use App\Models\Note;
use App\Models\Reminder;
use App\Models\Team;
use App\Models\User;
use App\Support\InquiryOptions;
use Carbon\CarbonImmutable;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

/**
 * Adds realistic test data to a hosted database. Unlike DatabaseSeeder it
 * does not need Faker (a dev-only package that is not installed in the
 * production image) and it never creates accounts with the password
 * "password". Safe to run more than once: it only adds inquiries.
 */
class SeedDemoData extends Command
{
    protected $signature = 'inquiry:demo-data
                            {--count=30 : Number of inquiries to create}
                            {--password=Portal2026Demo : Password for the demo manager and agents}';

    protected $description = 'Create demo teams, staff and inquiries for testing a hosted deployment';

    private const PEOPLE = [
        ['Aarav Shah', 'Brightline Logistics'], ['Priya Nair', 'Nimbus Health'],
        ['Rohan Mehta', 'Quantum Retail'], ['Sneha Kulkarni', 'Evergreen Foods'],
        ['Vikram Rao', 'Atlas Engineering'], ['Ananya Iyer', 'Lotus Education'],
        ['Karan Malhotra', 'Pinnacle Finance'], ['Meera Joshi', 'Harbor Travel'],
        ['Arjun Desai', 'Solaris Energy'], ['Kavya Reddy', 'Crescent Media'],
        ['Neha Gupta', 'Orbit Software'], ['Rahul Verma', 'Summit Realty'],
        ['Isha Kapoor', 'Willow Interiors'], ['Aditya Bose', 'Redwood Manufacturing'],
        ['Pooja Pillai', 'Coral Hospitality'], ['Siddharth Jain', 'Vertex Consulting'],
        ['Tanvi Patil', 'Maple Clinics'], ['Nikhil Saxena', 'Ironclad Security'],
        ['Riya Chatterjee', 'Bluebird Apparel'], ['Manish Agarwal', 'Keystone Builders'],
    ];

    private const SUBJECTS = [
        ['Pricing for 50-user plan', 'We are evaluating tools for our sales team of about 50 people. Could you share pricing and any annual discount options?'],
        ['Request for product demo', 'We would like a live demo of the inquiry tracking and reporting features for our operations team next week.'],
        ['Integration with our CRM', 'Does your platform integrate with our existing CRM? We need inquiries to sync automatically in both directions.'],
        ['Bulk onboarding support', 'We plan to migrate around 3,000 historical leads. What onboarding and data import support do you provide?'],
        ['Custom reporting requirements', 'Our management needs weekly conversion reports by source and region. Is custom reporting available?'],
        ['Partnership opportunity', 'We are a reseller in our region and would like to discuss a partnership for offering your product to our clients.'],
        ['Security and compliance questions', 'Before procurement, our IT team needs details on data storage, encryption, access control and audit logs.'],
        ['Trial extension request', 'Our trial ends soon but key stakeholders have not reviewed it yet. Could the trial be extended by two weeks?'],
        ['Multi-branch rollout', 'We have six branches and need separate teams with shared reporting. Please advise on the recommended setup.'],
        ['Support response times', 'What are your support hours and response time commitments for priority issues?'],
    ];

    private const NOTES = [
        'Customer is comparing three providers. Emphasise implementation support and reporting.',
        'Budget approval expected this month. Follow up after their stakeholder meeting.',
        'Technical team needs an integration walkthrough before commercial discussions.',
        'Potential multi-team rollout. Prepare a phased implementation proposal.',
        'Prefers email contact in the morning. Confirm requirements before quoting.',
    ];

    private const REMINDERS = [
        'Follow up on proposal', 'Arrange product demonstration', 'Confirm budget and timeline',
        'Send integration documentation', 'Check stakeholder feedback',
    ];

    public function handle(): int
    {
        $count = max(1, min(500, (int) $this->option('count')));
        $password = (string) $this->option('password');

        DB::transaction(function () use ($count, $password): void {
            $sales = Team::query()->firstOrCreate(['name' => 'Sales']);
            $success = Team::query()->firstOrCreate(['name' => 'Customer Success']);

            $manager = $this->staff('manager@demo.test', 'Taylor Brooks', 'manager', $sales->id, $password);
            $agents = collect([
                $this->staff('agent1@demo.test', 'Jordan Patel', 'agent', $sales->id, $password),
                $this->staff('agent2@demo.test', 'Sam Rivera', 'agent', $sales->id, $password),
                $this->staff('agent3@demo.test', 'Casey Chen', 'agent', $success->id, $password),
            ]);

            $now = CarbonImmutable::now();
            $statuses = InquiryOptions::STATUSES;
            $sources = InquiryOptions::SOURCES;
            $priorities = InquiryOptions::PRIORITIES;

            for ($i = 0; $i < $count; $i++) {
                [$name, $company] = self::PEOPLE[$i % count(self::PEOPLE)];
                [$subject, $message] = self::SUBJECTS[$i % count(self::SUBJECTS)];
                $status = $statuses[$i % count($statuses)];
                $source = $sources[intdiv($i, 2) % count($sources)];
                $assignee = $i % 7 === 0 ? null : $agents[$i % $agents->count()];
                $author = $assignee ?? $manager;
                $closed = InquiryOptions::isClosed($status);

                $createdAt = $i < 4
                    ? $now->subMinutes(($i + 1) * 20)
                    : $now->subDays(1 + (($i * 5) % 45))->subMinutes($i * 7);

                $inquiry = new Inquiry();
                $inquiry->forceFill([
                    'name' => $name,
                    'email' => strtolower(str_replace(' ', '.', $name)).'@example.com',
                    'phone' => '+91 98'.str_pad((string) (20000000 + $i * 137), 8, '0', STR_PAD_LEFT),
                    'company' => $company,
                    'subject' => $subject,
                    'message' => $message,
                    'source' => $source,
                    'utm_source' => $source === 'campaign' ? 'google' : null,
                    'utm_medium' => $source === 'campaign' ? 'cpc' : null,
                    'utm_campaign' => $source === 'campaign' ? 'spring-demo' : null,
                    'status' => $status,
                    'priority' => $priorities[intdiv($i, 3) % count($priorities)],
                    'assigned_to' => $assignee?->id,
                    'closed_at' => $closed ? $createdAt->addDays(3)->min($now) : null,
                    'created_at' => $createdAt,
                    'updated_at' => $createdAt,
                ])->save();

                $this->message($inquiry, 'customer', null, $message, $createdAt);
                $this->activity($inquiry, null, 'created', "Inquiry received through the {$source} channel.", $createdAt);

                if ($assignee !== null) {
                    $this->activity($inquiry, $manager, 'assigned', "Inquiry assigned to {$assignee->name}.", $createdAt->addMinute()->min($now));
                }

                $replyAt = $createdAt->addMinutes(30)->min($now);

                if ($status !== 'new') {
                    $this->message($inquiry, 'staff', $author->id,
                        'Thank you for reaching out. I have reviewed your requirements and will share the relevant details shortly. '
                        .'Could you suggest a convenient time for a short call?', $replyAt);
                    $this->activity($inquiry, $author, 'status_changed', "Status changed from new to {$status}.", $replyAt);
                }

                if ($i % 3 === 0) {
                    $this->message($inquiry, 'customer', null,
                        'Thanks for the quick response. Our team is available this week. Please send the product overview before the call.',
                        $replyAt->addMinutes(45)->min($now));
                }

                if ($i % 2 === 0) {
                    Note::query()->forceCreate([
                        'inquiry_id' => $inquiry->id,
                        'user_id' => $author->id,
                        'body' => self::NOTES[$i % count(self::NOTES)],
                        'is_internal' => true,
                        'created_at' => $replyAt,
                        'updated_at' => $replyAt,
                    ]);
                    $this->activity($inquiry, $author, 'note_added', 'Internal note added.', $replyAt);
                }

                // Mix of overdue, upcoming and completed reminders.
                $remindAt = match (true) {
                    $closed => $inquiry->closed_at,
                    $i % 3 === 0 => $now->subHours(2 + ($i % 20)),
                    default => $now->addDays(1 + ($i % 7))->setTime(10 + ($i % 6), 0),
                };

                Reminder::query()->forceCreate([
                    'inquiry_id' => $inquiry->id,
                    'user_id' => $author->id,
                    'title' => $closed ? 'Confirm final outcome with customer' : self::REMINDERS[$i % count(self::REMINDERS)],
                    'remind_at' => $remindAt,
                    'is_completed' => $closed,
                    'notified_at' => $closed ? $remindAt : null,
                    'created_at' => $replyAt,
                    'updated_at' => $replyAt,
                ]);
                $this->activity($inquiry, $author, 'reminder_added', 'Follow-up reminder created.', $replyAt);
            }
        });

        $this->info("Created {$count} demo inquiries.");
        $this->line('Demo staff logins (password: '.$this->option('password').'):');
        $this->line('  manager@demo.test   (manager)');
        $this->line('  agent1@demo.test, agent2@demo.test, agent3@demo.test   (agents)');

        return self::SUCCESS;
    }

    private function staff(string $email, string $name, string $role, int $teamId, string $password): User
    {
        return User::query()->firstOrCreate(
            ['email' => $email],
            [
                'name' => $name,
                'password' => Hash::make($password),
                'role' => $role,
                'team_id' => $teamId,
                'is_active' => true,
            ]
        );
    }

    private function message(Inquiry $inquiry, string $sender, ?int $userId, string $body, CarbonImmutable $at): void
    {
        InquiryMessage::query()->forceCreate([
            'inquiry_id' => $inquiry->id,
            'sender_type' => $sender,
            'user_id' => $userId,
            'body' => $body,
            'created_at' => $at,
            'updated_at' => $at,
        ]);
    }

    private function activity(Inquiry $inquiry, ?User $user, string $action, string $description, CarbonImmutable $at): void
    {
        ActivityLog::query()->forceCreate([
            'inquiry_id' => $inquiry->id,
            'user_id' => $user?->id,
            'action' => $action,
            'description' => $description,
            'old_values' => null,
            'new_values' => null,
            'created_at' => $at,
            'updated_at' => $at,
        ]);
    }
}