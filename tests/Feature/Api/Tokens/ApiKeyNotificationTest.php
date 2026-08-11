<?php

declare(strict_types=1);

namespace Tests\Feature\Api\Tokens;

use App\Models\User;
use App\Notifications\ApiKeyCreatedNotification;
use App\Notifications\ApiKeyRevokedNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class ApiKeyNotificationTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_sends_notification_on_token_creation(): void
    {
        Notification::fake();

        $user = User::factory()->create();

        $this->actingAs($user)
            ->withHeaders(['Idempotency-Key' => 'test-key-notification'])
            ->postJson('/api/v1/api-tokens', [
                'name' => 'Production Key',
                'scopes' => ['account:read'],
            ])
            ->assertCreated();

        Notification::assertSentTo(
            $user,
            ApiKeyCreatedNotification::class,
            fn ($notification): bool => $notification->toArray($user)['token_name'] === 'Production Key'
        );
    }

    public function test_it_sends_notification_on_token_revocation(): void
    {
        Notification::fake();

        $user = User::factory()->create();
        $apiKey = $user->createToken('To Be Deleted', ['account:read']);

        $this->actingAs($user)
            ->deleteJson('/api/v1/api-tokens/'.$apiKey->accessToken->id)
            ->assertOk();

        Notification::assertSentTo(
            $user,
            ApiKeyRevokedNotification::class,
            fn ($notification): bool => $notification->toArray($user)['token_name'] === 'To Be Deleted'
        );
    }
}
