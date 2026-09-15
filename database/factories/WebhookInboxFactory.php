<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\WebhookInboxState;
use App\Models\WebhookInbox;
use Illuminate\Database\Eloquent\Factories\Factory;

class WebhookInboxFactory extends Factory
{
    protected $model = WebhookInbox::class;

    /**
     * Define the model's default state.
     *
     * @return array
     */
    public function definition()
    {
        return [
            'provider' => 'zoom',
            'event_key' => $this->faker->unique()->sha256(),
            'event_type' => $this->faker->randomElement(['meeting.updated', 'meeting.deleted', 'meeting.started', 'meeting.ended']),
            'provider_request_id' => $this->faker->uuid(),
            'provider_occurred_at' => $this->faker->unixTime(),
            'payload' => [
                'meetingId' => $this->faker->numberBetween(1000000000, 9999999999),
                'changes' => [
                    'topic' => $this->faker->sentence(4),
                    'duration' => $this->faker->randomElement([15, 30, 45, 60]),
                ],
            ],
            'state' => WebhookInboxState::Received->value,
            'attempts' => 0,
        ];
    }
}
