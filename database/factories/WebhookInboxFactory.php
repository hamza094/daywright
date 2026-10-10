<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\WebhookInboxState;
use App\Models\WebhookInbox;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<WebhookInbox>
 *
 * @method WebhookInbox create(array $attributes = [])
 * @method WebhookInbox make(array $attributes = [])
 */
final class WebhookInboxFactory extends Factory
{
    protected $model = WebhookInbox::class;

    /**
     * @return array{
     *     provider: string,
     *     event_key: string,
     *     event_type: string,
     *     provider_request_id: string,
     *     provider_occurred_at: int,
     *     payload: array,
     *     state: WebhookInboxState,
     *     attempts: int,
     * }
     */
    public function definition(): array
    {
        $eventType = $this->faker->randomElement(['meeting.updated', 'meeting.deleted', 'meeting.started', 'meeting.ended']);

        return [
            'provider' => 'zoom',
            'event_key' => $this->faker->unique()->sha256(),
            'event_type' => $eventType,
            'provider_request_id' => $this->faker->uuid(),
            'provider_occurred_at' => $this->faker->unixTime() * 1000,
            'payload' => match ($eventType) {
                'meeting.updated' => [
                    'meetingId' => $this->faker->numberBetween(1000000000, 9999999999),
                    'changes' => [
                        'topic' => $this->faker->sentence(4),
                        'duration' => $this->faker->randomElement([15, 30, 45, 60]),
                    ],
                ],
                'meeting.deleted' => [
                    'meetingId' => $this->faker->numberBetween(1000000000, 9999999999),
                    'requestId' => $this->faker->uuid(),
                ],
                'meeting.started' => [
                    'meetingId' => $this->faker->numberBetween(1000000000, 9999999999),
                    'startTime' => $this->faker->dateTimeThisYear()->format('Y-m-d\TH:i:s\Z'),
                    'requestId' => $this->faker->uuid(),
                ],
                'meeting.ended' => [
                    'meetingId' => $this->faker->numberBetween(1000000000, 9999999999),
                    'startTime' => $this->faker->dateTimeThisYear()->format('Y-m-d\TH:i:s\Z'),
                    'endTime' => $this->faker->dateTimeThisYear()->format('Y-m-d\TH:i:s\Z'),
                    'requestId' => $this->faker->uuid(),
                ],
            },
            'state' => WebhookInboxState::Received,
            'attempts' => 0,
        ];
    }

    /**
     * Set a specific event type.
     */
    public function forEventType(string $eventType): self
    {
        return $this->state(fn (array $attributes) => [
            'event_type' => $eventType,
            'payload' => match ($eventType) {
                'meeting.updated' => [
                    'meetingId' => $this->faker->numberBetween(1000000000, 9999999999),
                    'changes' => [
                        'topic' => $this->faker->sentence(4),
                        'duration' => $this->faker->randomElement([15, 30, 45, 60]),
                    ],
                ],
                'meeting.deleted' => [
                    'meetingId' => $this->faker->numberBetween(1000000000, 9999999999),
                    'requestId' => $this->faker->uuid(),
                ],
                'meeting.started' => [
                    'meetingId' => $this->faker->numberBetween(1000000000, 9999999999),
                    'startTime' => $this->faker->dateTimeThisYear()->format('Y-m-d\TH:i:s\Z'),
                    'requestId' => $this->faker->uuid(),
                ],
                'meeting.ended' => [
                    'meetingId' => $this->faker->numberBetween(1000000000, 9999999999),
                    'startTime' => $this->faker->dateTimeThisYear()->format('Y-m-d\TH:i:s\Z'),
                    'endTime' => $this->faker->dateTimeThisYear()->format('Y-m-d\TH:i:s\Z'),
                    'requestId' => $this->faker->uuid(),
                ],
            },
        ]);
    }
}
