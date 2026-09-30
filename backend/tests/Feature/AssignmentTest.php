<?php

namespace Tests\Feature;

use App\Models\Inquiry;
use App\Models\Setting;
use App\Models\User;
use App\Services\AssignmentService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AssignmentTest extends TestCase
{
    use RefreshDatabase;

    public function test_round_robin_cycles_through_active_agents(): void
    {
        $first = User::factory()->create();
        $second = User::factory()->create();
        $third = User::factory()->create();

        $assignment = app(AssignmentService::class);

        $actual = [];

        for ($index = 0; $index < 6; $index++) {
            $actual[] = $assignment->nextAgent()?->id;
        }

        $this->assertSame([
            $first->id,
            $second->id,
            $third->id,
            $first->id,
            $second->id,
            $third->id,
        ], $actual);
    }

    public function test_assignment_excludes_inactive_users_and_managers(): void
    {
        User::factory()->inactive()->create();
        User::factory()->manager()->create();
        User::factory()->admin()->create();

        $agent = User::factory()->create();

        $this->assertSame(
            $agent->id,
            app(AssignmentService::class)->nextAgent()?->id
        );
    }

    public function test_least_loaded_counts_only_unresolved_inquiries(): void
    {
        Setting::query()->create([
            'key' => 'inquiry.strategy',
            'value' => 'least_loaded',
        ]);

        $busy = User::factory()->create();
        $available = User::factory()->create();

        Inquiry::factory()->count(3)->open()->assignedTo($busy)->create();

        Inquiry::factory()->create([
            'status' => 'pending',
            'assigned_to' => $available->id,
        ]);

        Inquiry::factory()
            ->count(5)
            ->won()
            ->assignedTo($available)
            ->create();

        $this->assertSame(
            $available->id,
            app(AssignmentService::class)->nextAgent()?->id
        );
    }

    public function test_assignment_can_be_disabled(): void
    {
        User::factory()->create();

        Setting::query()->create([
            'key' => 'inquiry.auto_assign',
            'value' => false,
        ]);

        $this->assertNull(app(AssignmentService::class)->nextAgent());
    }

    public function test_no_eligible_agents_leaves_inquiry_unassigned(): void
    {
        User::factory()->manager()->create();

        $response = $this->postJson(
            '/api/v1/public/inquiries',
            $this->inquiryPayload()
        );

        $response->assertCreated();

        $this->assertNull(Inquiry::query()->firstOrFail()->assigned_to);
    }
}
