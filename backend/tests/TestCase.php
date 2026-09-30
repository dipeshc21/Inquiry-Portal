<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Queue;

abstract class TestCase extends BaseTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Cache::flush();
        Mail::fake();
        Queue::fake();
    }

    protected function inquiryPayload(array $overrides = []): array
    {
        return array_replace([
            'name' => 'Jamie Carter',
            'email' => 'jamie@example.com',
            'phone' => '+1 202 555 0134',
            'company' => 'Carter Operations',
            'subject' => 'Request a product demonstration',
            'message' => 'We would like to arrange a demonstration for our '
                .'sales and customer support teams.',
            'source' => 'website',
            'honeypot' => '',
        ], $overrides);
    }
}
