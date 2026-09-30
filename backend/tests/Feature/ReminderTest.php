<?php

namespace Tests\Feature;

use App\Jobs\DeliverReminder;
use App\Mail\ReminderNotification;
use App\Models\Inquiry;
use App\Models\Reminder;
use App\Models\User;
use App\Services\ActivityLogger;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Queue;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ReminderTest extends TestCase
{
    use RefreshDatabase;

    public function test_command_dispatches_due_incomplete_reminders(): void
    {
        $agent = User::factory()->create();
        $inquiry = Inquiry::factory()->assignedTo($agent)->create();

        $due = $this->reminder($inquiry, $agent, [
            'remind_at' => now()->subMinute(),
        ]);

        $this->reminder($inquiry, $agent, [
            'remind_at' => now()->addDay(),
        ]);

        $this->reminder($inquiry, $agent, [
            'remind_at' => now()->subHour(),
            'is_completed' => true,
        ]);

        $this->artisan('reminders:send')->assertSuccessful();

        Queue::assertPushed(
            DeliverReminder::class,
            fn (DeliverReminder $job): bool => $job->reminderId === $due->id
        );

        Queue::assertPushed(DeliverReminder::class, 1);
    }

    public function test_delivery_marks_reminder_notified_and_avoids_resending(): void
    {
        $agent = User::factory()->create();
        $inquiry = Inquiry::factory()->assignedTo($agent)->create();

        $reminder = $this->reminder($inquiry, $agent, [
            'remind_at' => now()->subMinute(),
        ]);

        $job = new DeliverReminder($reminder->id);
        $job->handle(app(ActivityLogger::class));
        $job->handle(app(ActivityLogger::class));

        Mail::assertSent(ReminderNotification::class, 1);

        $this->assertNotNull($reminder->fresh()->notified_at);

        $this->assertDatabaseHas('activity_logs', [
            'inquiry_id' => $inquiry->id,
            'action' => 'reminder_notified',
        ]);
    }

    public function test_reassigned_inquiry_is_not_emailed_to_previous_agent(): void
    {
        $previousAgent = User::factory()->create();
        $currentAgent = User::factory()->create();

        $inquiry = Inquiry::factory()
            ->assignedTo($currentAgent)
            ->create();

        $reminder = $this->reminder($inquiry, $previousAgent, [
            'remind_at' => now()->subMinute(),
        ]);

        (new DeliverReminder($reminder->id))
            ->handle(app(ActivityLogger::class));

        Mail::assertNothingSent();

        $this->assertNull($reminder->fresh()->notified_at);
    }

    public function test_agent_can_complete_their_own_reminder(): void
    {
        $agent = User::factory()->create();
        $inquiry = Inquiry::factory()->assignedTo($agent)->create();
        $reminder = $this->reminder($inquiry, $agent);

        Sanctum::actingAs($agent);

        $this->patchJson(
            '/api/v1/reminders/'.$reminder->id.'/complete'
        )
            ->assertOk()
            ->assertJsonPath('data.is_completed', true);

        $this->assertTrue($reminder->fresh()->is_completed);
    }

    public function test_reminder_list_only_returns_current_users_reminders(): void
    {
        $agent = User::factory()->create();
        $otherAgent = User::factory()->create();

        $ownedInquiry = Inquiry::factory()->assignedTo($agent)->create();
        $otherInquiry = Inquiry::factory()->assignedTo($otherAgent)->create();

        $owned = $this->reminder($ownedInquiry, $agent);
        $this->reminder($otherInquiry, $otherAgent);

        Sanctum::actingAs($agent);

        $this->getJson('/api/v1/reminders/upcoming')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $owned->id);
    }

    private function reminder(
        Inquiry $inquiry,
        User $user,
        array $overrides = []
    ): Reminder {
        return Reminder::query()->create(array_replace([
            'inquiry_id' => $inquiry->id,
            'user_id' => $user->id,
            'title' => 'Follow up on the proposal',
            'remind_at' => now()->addDay(),
            'is_completed' => false,
            'notified_at' => null,
        ], $overrides));
    }
}
