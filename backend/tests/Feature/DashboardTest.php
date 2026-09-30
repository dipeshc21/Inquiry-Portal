<?php

namespace Tests\Feature;

use App\Models\Inquiry;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Support\Facades\DB;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class DashboardTest extends TestCase
{
    // Real commits are needed to exercise after-commit cache invalidation.
    use DatabaseMigrations;

    public function test_dashboard_returns_correct_counts_and_conversion(): void
    {
        $manager = User::factory()->manager()->create();

        foreach ([
            'new',
            'contacted',
            'qualified',
            'pending',
            'won',
            'lost',
        ] as $status) {
            Inquiry::factory()->create([
                'status' => $status,
                'source' => 'website',
                'created_at' => now(),
            ]);
        }

        Sanctum::actingAs($manager);

        $this->getJson('/api/v1/dashboard/stats')
            ->assertOk()
            ->assertJsonPath('data.total', 6)
            ->assertJsonPath('data.open', 3)
            ->assertJsonPath('data.pending', 1)
            ->assertJsonPath('data.closed', 2)
            ->assertJsonPath('data.won', 1)
            ->assertJsonPath('data.lost', 1)
            ->assertJsonPath('data.new_today', 6)
            ->assertJsonPath('data.conversion_rate', 50)
            ->assertJsonCount(6, 'data.by_status')
            ->assertJsonCount(6, 'data.by_source')
            ->assertJsonCount(30, 'data.per_day');
    }

    public function test_agent_dashboard_is_limited_to_assigned_inquiries(): void
    {
        $agent = User::factory()->create();
        $otherAgent = User::factory()->create();

        Inquiry::factory()->open()->assignedTo($agent)->create();
        Inquiry::factory()->won()->assignedTo($agent)->create();

        Inquiry::factory()
            ->count(3)
            ->open()
            ->assignedTo($otherAgent)
            ->create();

        Sanctum::actingAs($agent);

        $this->getJson('/api/v1/dashboard/stats')
            ->assertOk()
            ->assertJsonPath('data.total', 2)
            ->assertJsonPath('data.open', 1)
            ->assertJsonPath('data.won', 1)
            ->assertJsonPath('data.my_open_assigned', 1)
            ->assertJsonPath('data.conversion_rate', 100);
    }

    public function test_dashboard_handles_zero_closed_inquiries(): void
    {
        Sanctum::actingAs(User::factory()->manager()->create());

        Inquiry::factory()->open()->create();

        $this->getJson('/api/v1/dashboard/stats')
            ->assertOk()
            ->assertJsonPath('data.closed', 0)
            ->assertJsonPath('data.conversion_rate', 0);
    }

    public function test_dashboard_cache_is_invalidated_after_creation(): void
    {
        Sanctum::actingAs(User::factory()->manager()->create());

        $this->getJson('/api/v1/dashboard/stats')
            ->assertOk()
            ->assertJsonPath('data.total', 0);

        Inquiry::factory()->open()->create();

        $this->getJson('/api/v1/dashboard/stats')
            ->assertOk()
            ->assertJsonPath('data.total', 1);
    }

    public function test_dashboard_counts_use_one_aggregate_query(): void
    {
        Sanctum::actingAs(User::factory()->manager()->create());

        Inquiry::factory()->count(3)->create();

        DB::enableQueryLog();
        DB::flushQueryLog();

        $this->getJson('/api/v1/dashboard/stats')->assertOk();

        $queries = collect(DB::getQueryLog())
            ->filter(fn (array $query): bool =>
                str_contains(strtolower($query['query']), 'sum(case when')
            );

        DB::disableQueryLog();

        $this->assertCount(1, $queries);
    }
}
