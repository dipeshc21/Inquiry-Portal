<?php

namespace Tests\Feature;

use App\Mail\StaffReply;
use App\Models\Inquiry;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class InquiryTest extends TestCase
{
    use RefreshDatabase;

    public function test_listing_honors_combined_filters(): void
    {
        $manager = User::factory()->manager()->create();
        $agent = User::factory()->create();

        $matching = Inquiry::factory()->create([
            'name' => 'Acme Buyer',
            'subject' => 'Acme implementation',
            'status' => 'qualified',
            'source' => 'referral',
            'priority' => 'high',
            'assigned_to' => $agent->id,
        ]);

        Inquiry::factory()->create([
            'name' => 'Acme Other Buyer',
            'status' => 'lost',
            'source' => 'referral',
            'priority' => 'high',
            'assigned_to' => $agent->id,
        ]);

        Inquiry::factory()->create([
            'name' => 'Unrelated Buyer',
            'status' => 'qualified',
            'source' => 'website',
            'priority' => 'medium',
        ]);

        Sanctum::actingAs($manager);

        $query = http_build_query([
            'q' => 'Acme',
            'status' => ['qualified'],
            'source' => ['referral'],
            'priority' => 'high',
            'assigned_to' => $agent->id,
            'per_page' => 15,
        ]);

        $this->getJson('/api/v1/inquiries?'.$query)
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $matching->id)
            ->assertJsonPath('meta.total', 1);
    }

    public function test_listing_filters_by_date_and_unassigned_state(): void
    {
        $manager = User::factory()->manager()->create();

        $matching = Inquiry::factory()->open()->create([
            'assigned_to' => null,
            'created_at' => '2026-03-15 10:30:00',
        ]);

        Inquiry::factory()->open()->create([
            'assigned_to' => null,
            'created_at' => '2026-03-16 00:00:00',
        ]);

        Inquiry::factory()->open()->create([
            'assigned_to' => User::factory()->create()->id,
            'created_at' => '2026-03-15 12:00:00',
        ]);

        Sanctum::actingAs($manager);

        $this->getJson('/api/v1/inquiries?'.http_build_query([
            'unassigned' => 'true',
            'date_from' => '2026-03-15',
            'date_to' => '2026-03-15',
        ]))
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $matching->id);
    }

    public function test_page_size_is_validated(): void
    {
        Sanctum::actingAs(User::factory()->manager()->create());

        $this->getJson('/api/v1/inquiries?per_page=101')
            ->assertUnprocessable()
            ->assertJsonValidationErrors('per_page');
    }

    public function test_status_change_sets_and_clears_closed_at(): void
    {
        $agent = User::factory()->create();

        $inquiry = Inquiry::factory()
            ->open()
            ->assignedTo($agent)
            ->create();

        Sanctum::actingAs($agent);

        $this->patchJson(
            '/api/v1/inquiries/'.$inquiry->id.'/status',
            [
                'status' => 'won',
                'note' => 'Customer approved the proposal.',
            ]
        )
            ->assertOk()
            ->assertJsonPath('data.status', 'won');

        $this->assertNotNull($inquiry->fresh()->closed_at);

        $this->assertDatabaseHas('notes', [
            'inquiry_id' => $inquiry->id,
            'user_id' => $agent->id,
            'body' => 'Customer approved the proposal.',
        ]);

        $this->assertDatabaseHas('activity_logs', [
            'inquiry_id' => $inquiry->id,
            'action' => 'status_changed',
        ]);

        $this->patchJson(
            '/api/v1/inquiries/'.$inquiry->id.'/status',
            ['status' => 'contacted']
        )
            ->assertOk()
            ->assertJsonPath('data.status', 'contacted')
            ->assertJsonPath('data.closed_at', null);

        $this->assertNull($inquiry->fresh()->closed_at);
    }

    public function test_staff_reply_is_saved_and_queued_for_email(): void
    {
        $agent = User::factory()->create();

        $inquiry = Inquiry::factory()
            ->open()
            ->assignedTo($agent)
            ->create();

        Sanctum::actingAs($agent);

        $this->postJson(
            '/api/v1/inquiries/'.$inquiry->id.'/messages',
            ['body' => 'We can arrange a demonstration tomorrow.']
        )
            ->assertCreated()
            ->assertJsonPath('data.sender_type', 'staff')
            ->assertJsonPath('data.user_id', $agent->id);

        $this->assertDatabaseHas('inquiry_messages', [
            'inquiry_id' => $inquiry->id,
            'sender_type' => 'staff',
            'body' => 'We can arrange a demonstration tomorrow.',
        ]);

        Mail::assertQueued(
            StaffReply::class,
            fn (StaffReply $mail): bool =>
                $mail->hasTo($inquiry->email)
                && $mail->inquiry->id === $inquiry->id
        );
    }

    public function test_admin_delete_is_a_soft_delete(): void
    {
        $admin = User::factory()->admin()->create();
        $inquiry = Inquiry::factory()->open()->create();

        Sanctum::actingAs($admin);

        $this->deleteJson('/api/v1/inquiries/'.$inquiry->id)
            ->assertOk();

        $this->assertSoftDeleted('inquiries', ['id' => $inquiry->id]);

        $this->assertDatabaseHas('activity_logs', [
            'inquiry_id' => $inquiry->id,
            'action' => 'deleted',
        ]);

        $this->getJson('/api/v1/inquiries/'.$inquiry->id)
            ->assertNotFound();
    }
}
