<?php

declare(strict_types=1);

namespace Tests\Feature\Api\V1\Admin;

use App\Interfaces\Paddle;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Mockery\MockInterface;
use Override;
use PHPUnit\Framework\Attributes\Test;
use RuntimeException;
use Tests\TestCase;

class PaddleTest extends TestCase
{
    use RefreshDatabase;

    private const string SUBSCRIPTIONS_ROUTE = '/api/v1/admin/subscriptions/list';

    private User $admin;

    #[Override]
    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::factory()->admin()->create();
        $this->enableTwoFactorForUser($this->admin);

        // Paddle config required for UserSubscriptionData DTO construction
        config([
            'services.paddle.vendor_id' => 12345,
            'services.paddle.vendor_auth_code' => 'test-auth-code',
            'services.paddle.results_per_page' => 10,
        ]);

        Sanctum::actingAs($this->admin);
    }

    // Authorization

    #[Test]
    public function non_admin_cannot_access_subscription_list(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $this->getJson(self::SUBSCRIPTIONS_ROUTE)
            ->assertForbidden();
    }

    // Success

    #[Test]
    public function admin_can_list_subscribed_users(): void
    {
        $this->mock(Paddle::class, function (MockInterface $mock): void {
            $mock->shouldReceive('SubscriptionUsersList')
                ->once()
                ->andReturn(collect([
                    ['id' => 1, 'email' => 'alice@example.com'],
                    ['id' => 2, 'email' => 'bob@example.com'],
                ]));
        });

        $response = $this->getJson(self::SUBSCRIPTIONS_ROUTE)
            ->assertOk()
            ->assertJsonStructure(['data']);

        $this->assertCount(2, $response->json('data'));
    }

    // Error Handling

    #[Test]
    public function returns_500_with_message_when_paddle_api_throws(): void
    {
        $this->mock(Paddle::class, function (MockInterface $mock): void {
            $mock->shouldReceive('SubscriptionUsersList')
                ->once()
                ->andThrow(new RuntimeException('Connection timed out'));
        });

        $this->getJson(self::SUBSCRIPTIONS_ROUTE)
            ->assertStatus(500)
            ->assertJsonStructure(['error'])
            ->assertJsonPath('error', 'Connection timed out');
    }

    private function enableTwoFactorForUser(User $user): void
    {
        $twoFactor = $user->createTwoFactorAuth();

        $twoFactor->forceFill([
            'label' => "DayWright:{$user->email}",
        ])->save();

        $user->enableTwoFactorAuth();
    }
}
