<?php

namespace Tests\Feature;

use App\Models\Inquiry;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class PublicInquiryTest extends TestCase
{
    use RefreshDatabase;

    public function test_customer_can_submit_an_inquiry(): void
    {
        $agent = User::factory()->create();

        $response = $this->postJson(
            '/api/v1/public/inquiries',
            $this->inquiryPayload([
                'source' => 'campaign',
                'utm_source' => 'google',
                'utm_medium' => 'cpc',
                'utm_campaign' => 'demo-request',
            ])
        );

        $response
            ->assertCreated()
            ->assertJsonPath('success', true)
            ->assertJsonStructure([
                'success',
                'message',
                'data' => ['reference_no'],
                'meta',
            ]);

        $reference = $response->json('data.reference_no');

        $this->assertMatchesRegularExpression(
            '/^INQ-\d{8}-\d{4}$/',
            $reference
        );

        $inquiry = Inquiry::query()
            ->where('reference_no', $reference)
            ->firstOrFail();

        $this->assertSame($agent->id, $inquiry->assigned_to);
        $this->assertSame('new', $inquiry->status);
        $this->assertSame('campaign', $inquiry->source);
        $this->assertSame('google', $inquiry->utm_source);

        $this->assertDatabaseHas('inquiry_messages', [
            'inquiry_id' => $inquiry->id,
            'sender_type' => 'customer',
            'user_id' => null,
            'body' => $this->inquiryPayload()['message'],
        ]);

        $this->assertDatabaseHas('activity_logs', [
            'inquiry_id' => $inquiry->id,
            'action' => 'created',
        ]);
    }

    public function test_submission_validates_required_fields(): void
    {
        $this->postJson('/api/v1/public/inquiries', [
            'email' => 'invalid-address',
            'message' => 'short',
        ])
            ->assertUnprocessable()
            ->assertJsonPath('success', false)
            ->assertJsonValidationErrors([
                'name',
                'email',
                'subject',
                'message',
            ]);

        $this->assertDatabaseCount('inquiries', 0);
    }

    public function test_honeypot_rejects_spam(): void
    {
        $this->postJson(
            '/api/v1/public/inquiries',
            $this->inquiryPayload(['honeypot' => 'spam link'])
        )
            ->assertUnprocessable()
            ->assertJsonValidationErrors('honeypot');

        $this->assertDatabaseCount('inquiries', 0);
    }

    public function test_public_submission_cannot_set_staff_fields(): void
    {
        $this->postJson(
            '/api/v1/public/inquiries',
            $this->inquiryPayload([
                'status' => 'won',
                'priority' => 'high',
                'assigned_to' => 999,
            ])
        )
            ->assertUnprocessable()
            ->assertJsonValidationErrors([
                'status',
                'priority',
                'assigned_to',
            ]);
    }

    public function test_submission_is_limited_to_five_requests_per_minute(): void
    {
        for ($index = 0; $index < 5; $index++) {
            $this->postJson(
                '/api/v1/public/inquiries',
                $this->inquiryPayload()
            )->assertCreated();
        }

        $this->postJson(
            '/api/v1/public/inquiries',
            $this->inquiryPayload()
        )
            ->assertStatus(429)
            ->assertJsonPath('success', false)
            ->assertHeader('Retry-After');

        $this->assertDatabaseCount('inquiries', 5);
    }

    public function test_public_form_stores_an_allowed_attachment(): void
    {
        Storage::fake('public');

        $response = $this
            ->withHeader('Accept', 'application/json')
            ->post('/api/v1/public/inquiries', [
                ...$this->inquiryPayload(),
                'files' => [
                    UploadedFile::fake()->create(
                        'requirements.pdf',
                        100,
                        'application/pdf'
                    ),
                ],
            ]);

        $response->assertCreated();

        $inquiry = Inquiry::query()->firstOrFail();
        $attachment = $inquiry->attachments()->firstOrFail();

        $this->assertNull($attachment->uploaded_by);
        $this->assertSame('requirements.pdf', $attachment->original_name);

        Storage::disk('public')->assertExists($attachment->stored_path);
    }

    public function test_oversized_attachment_is_rejected(): void
    {
        Storage::fake('public');

        $this->withHeader('Accept', 'application/json')
            ->post('/api/v1/public/inquiries', [
                ...$this->inquiryPayload(),
                'files' => [
                    UploadedFile::fake()->create(
                        'large.pdf',
                        5121,
                        'application/pdf'
                    ),
                ],
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('files.0');

        $this->assertDatabaseCount('inquiries', 0);
    }
}
