<?php

namespace Tests\Feature;

use App\Models\Inquiry;
use App\Models\Note;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class AuthorizationTest extends TestCase
{
    use RefreshDatabase;

    public function test_agent_only_sees_assigned_inquiries(): void
    {
        $agent = User::factory()->create();
        $otherAgent = User::factory()->create();

        $owned = Inquiry::factory()->assignedTo($agent)->create();
        Inquiry::factory()->assignedTo($otherAgent)->create();
        Inquiry::factory()->create(['assigned_to' => null]);

        Sanctum::actingAs($agent);

        $this->getJson('/api/v1/inquiries')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $owned->id);
    }

    public function test_agent_cannot_view_or_edit_another_agents_inquiry(): void
    {
        $agent = User::factory()->create();

        $inquiry = Inquiry::factory()
            ->assignedTo(User::factory()->create())
            ->create();

        Sanctum::actingAs($agent);

        $this->getJson('/api/v1/inquiries/'.$inquiry->id)
            ->assertForbidden();

        $this->putJson('/api/v1/inquiries/'.$inquiry->id, [
            'subject' => 'Unauthorized edit',
        ])->assertForbidden();

        $this->patchJson('/api/v1/inquiries/'.$inquiry->id.'/status', [
            'status' => 'won',
        ])->assertForbidden();
    }

    public function test_agent_cannot_delete_assign_or_export(): void
    {
        $agent = User::factory()->create();
        $inquiry = Inquiry::factory()->assignedTo($agent)->create();

        Sanctum::actingAs($agent);

        $this->deleteJson('/api/v1/inquiries/'.$inquiry->id)
            ->assertForbidden();

        $this->patchJson('/api/v1/inquiries/'.$inquiry->id.'/assign', [
            'assigned_to' => null,
        ])->assertForbidden();

        $this->getJson('/api/v1/inquiries/export')
            ->assertForbidden();
    }

    public function test_manager_cannot_manage_users_or_delete_inquiries(): void
    {
        $manager = User::factory()->manager()->create();
        $inquiry = Inquiry::factory()->create();

        Sanctum::actingAs($manager);

        $this->getJson('/api/v1/users')->assertForbidden();

        $this->deleteJson('/api/v1/inquiries/'.$inquiry->id)
            ->assertForbidden();

        $this->getJson('/api/v1/inquiries/'.$inquiry->id)
            ->assertOk();
    }

    public function test_note_edit_requires_owner_or_admin(): void
    {
        $agent = User::factory()->create();
        $manager = User::factory()->manager()->create();

        $inquiry = Inquiry::factory()->assignedTo($agent)->create();

        $note = Note::query()->create([
            'inquiry_id' => $inquiry->id,
            'user_id' => $manager->id,
            'body' => 'Manager-owned note.',
            'is_internal' => true,
        ]);

        Sanctum::actingAs($agent);

        $this->putJson('/api/v1/notes/'.$note->id, [
            'body' => 'Unauthorized replacement.',
        ])->assertForbidden();

        Sanctum::actingAs(User::factory()->admin()->create());

        $this->putJson('/api/v1/notes/'.$note->id, [
            'body' => 'Administrator correction.',
        ])
            ->assertOk()
            ->assertJsonPath('data.body', 'Administrator correction.');
    }

    public function test_attachment_download_requires_inquiry_access(): void
    {
        Storage::fake('public');

        $owner = User::factory()->create();
        $otherAgent = User::factory()->create();
        $inquiry = Inquiry::factory()->assignedTo($owner)->create();

        Storage::disk('public')->put(
            'attachments/'.$inquiry->id.'/document.pdf',
            '%PDF-1.4 test'
        );

        $attachment = $inquiry->attachments()->create([
            'uploaded_by' => $owner->id,
            'original_name' => 'document.pdf',
            'stored_path' => 'attachments/'.$inquiry->id.'/document.pdf',
            'mime_type' => 'application/pdf',
            'size' => 13,
        ]);

        Sanctum::actingAs($otherAgent);

        $this->get('/api/v1/attachments/'.$attachment->id.'/download', [
            'Accept' => 'application/json',
        ])->assertForbidden();

        Sanctum::actingAs($owner);

        $this->get('/api/v1/attachments/'.$attachment->id.'/download')
            ->assertOk()
            ->assertDownload('document.pdf');
    }

    public function test_inactive_user_is_rejected_on_authenticated_routes(): void
    {
        $user = User::factory()->inactive()->create();

        Sanctum::actingAs($user);

        $this->getJson('/api/v1/inquiries')->assertUnauthorized();
    }
}
