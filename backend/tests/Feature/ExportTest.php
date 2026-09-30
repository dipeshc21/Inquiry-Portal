<?php

namespace Tests\Feature;

use App\Models\Inquiry;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ExportTest extends TestCase
{
    use RefreshDatabase;

    public function test_manager_can_export_filtered_inquiries(): void
    {
        $manager = User::factory()->manager()->create();
        $agent = User::factory()->create(['name' => 'Jordan Agent']);

        $included = Inquiry::factory()->assignedTo($agent)->create([
            'status' => 'qualified',
            'source' => 'referral',
            'name' => 'Included Customer',
        ]);

        $excluded = Inquiry::factory()->create([
            'status' => 'lost',
            'source' => 'website',
            'name' => 'Excluded Customer',
        ]);

        Sanctum::actingAs($manager);

        $response = $this->get('/api/v1/inquiries/export?'.http_build_query([
            'status' => ['qualified'],
            'source' => ['referral'],
        ]));

        $response->assertOk();

        $csv = $response->streamedContent();

        $this->assertStringContainsString($included->reference_no, $csv);
        $this->assertStringContainsString('Included Customer', $csv);
        $this->assertStringContainsString('Jordan Agent', $csv);
        $this->assertStringNotContainsString($excluded->reference_no, $csv);
        $this->assertStringNotContainsString('Excluded Customer', $csv);
    }

    public function test_selected_ids_limit_the_export(): void
    {
        $admin = User::factory()->admin()->create();
        $selected = Inquiry::factory()->create();
        $unselected = Inquiry::factory()->create();

        Sanctum::actingAs($admin);

        $response = $this->get('/api/v1/inquiries/export?'.http_build_query([
            'ids' => [$selected->id],
        ]));

        $response->assertOk();

        $csv = $response->streamedContent();

        $this->assertStringContainsString($selected->reference_no, $csv);
        $this->assertStringNotContainsString($unselected->reference_no, $csv);
    }

    public function test_export_neutralizes_spreadsheet_formulas(): void
    {
        Sanctum::actingAs(User::factory()->admin()->create());

        Inquiry::factory()->create([
            'name' => '=SUM(1,1)',
            'company' => '@SUM(1,1)',
        ]);

        $response = $this->get('/api/v1/inquiries/export');

        $response->assertOk();

        $csv = $response->streamedContent();

        $this->assertStringContainsString("'=SUM(1,1)", $csv);
        $this->assertStringContainsString("'@SUM(1,1)", $csv);
    }

    public function test_agent_cannot_export(): void
    {
        Sanctum::actingAs(User::factory()->create());

        $this->getJson('/api/v1/inquiries/export')->assertForbidden();
    }
}
