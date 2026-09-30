<?php

namespace Database\Factories;

use App\Models\Inquiry;
use App\Models\User;
use App\Support\InquiryOptions;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Inquiry>
 */
class InquiryFactory extends Factory
{
    protected $model = Inquiry::class;

    public function definition(): array
    {
        $createdAt = CarbonImmutable::instance(
            fake()->dateTimeBetween('-90 days', 'now')
        );

        $status = fake()->randomElement(InquiryOptions::STATUSES);
        $source = fake()->randomElement(InquiryOptions::SOURCES);
        $closedAt = null;

        if (InquiryOptions::isClosed($status)) {
            $closedAt = $createdAt
                ->addDays(fake()->numberBetween(0, 12))
                ->min(CarbonImmutable::now());
        }

        $subjects = [
            'Request for an enterprise software quotation',
            'Product demonstration for our sales team',
            'Annual support and maintenance enquiry',
            'Integration with our existing CRM',
            'Pricing for a multi-location deployment',
            'Partnership and reseller programme',
            'Migration from our current platform',
            'Training for customer support staff',
            'Custom reporting and analytics requirements',
            'Security and data hosting requirements',
        ];

        $messages = [
            'We are evaluating solutions for our growing team. Please share '
                .'your product information, pricing options, and available '
                .'times for a demonstration.',
            'Our business needs a central system to manage customer requests. '
                .'We would like to discuss implementation timelines, user '
                .'licensing, and integration with our current tools.',
            'Please provide a quotation for your services, including setup, '
                .'staff training, and ongoing support. We expect to make a '
                .'decision within the next month.',
            'We are planning to replace our current workflow and would like '
                .'to understand your reporting, access control, and data '
                .'migration capabilities.',
            'A colleague recommended your team. Could you arrange a call '
                .'to discuss our requirements and explain the available '
                .'plans for a company of our size?',
        ];

        return [
            // InquiryObserver generates the reference inside a transaction.
            'name' => fake()->name(),
            'email' => fake()->safeEmail(),
            'phone' => fake()->optional(0.85)->numerify('+1##########'),
            'company' => fake()->optional(0.8)->company(),
            'subject' => fake()->randomElement($subjects),
            'message' => fake()->randomElement($messages),
            'source' => $source,
            'utm_source' => $source === 'campaign' ? 'google' : null,
            'utm_medium' => $source === 'campaign' ? 'cpc' : null,
            'utm_campaign' => $source === 'campaign'
                ? fake()->randomElement([
                    'enterprise-demo',
                    'autumn-growth',
                    'support-platform',
                ])
                : null,
            'status' => $status,
            'priority' => fake()->randomElement(InquiryOptions::PRIORITIES),
            'assigned_to' => null,
            'ip_address' => fake()->ipv4(),
            'closed_at' => $closedAt,
            'created_at' => $createdAt,
            'updated_at' => $closedAt ?? $createdAt,
        ];
    }

    public function assignedTo(User $user): static
    {
        return $this->state(fn (): array => [
            'assigned_to' => $user->id,
        ]);
    }

    public function open(): static
    {
        return $this->state(fn (): array => [
            'status' => 'new',
            'closed_at' => null,
        ]);
    }

    public function won(): static
    {
        return $this->state(fn (): array => [
            'status' => 'won',
            'closed_at' => CarbonImmutable::now(),
            'updated_at' => CarbonImmutable::now(),
        ]);
    }

    public function lost(): static
    {
        return $this->state(fn (): array => [
            'status' => 'lost',
            'closed_at' => CarbonImmutable::now(),
            'updated_at' => CarbonImmutable::now(),
        ]);
    }
}
