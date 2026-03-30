<?php

declare(strict_types=1);

namespace Tests\Feature\Api\Middleware;

use App\Models\Project;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Testing\TestResponse;
use InvalidArgumentException;
use Laravel\Sanctum\Sanctum;
use Override;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use Tests\Helpers\FixtureHelpers;
use Tests\Helpers\SubscriptionHelpers;
use Tests\TestCase;
use Tests\Traits\AuthenticatedProjectHelpers;

class CheckSubscriptionMiddlewareTest extends TestCase
{
    use AuthenticatedProjectHelpers, FixtureHelpers, RefreshDatabase, SubscriptionHelpers;

    private const string STATE_SUBSCRIBED = 'subscribed';

    private const string STATE_GRACE_PERIOD = 'grace_period';

    private const string STATE_TRIAL = 'trial';

    private const string STATE_POST_GRACE = 'post_grace';

    private const string STATE_EXPIRED_TRIAL = 'expired_trial';

    private const string STATE_FREE = 'free';

    #[Override]
    protected function setUp(): void
    {
        parent::setUp();

        config()->set('services.paddle.monthly', 101);
        config()->set('services.paddle.yearly', 202);

        // FixtureHelpers seeds the task statuses required by the task factories.
        $this->createTaskStatuses();

        // AuthenticatedProjectHelpers sets up a user, a project, and Sanctum acting user.
        $this->setUpAuthenticatedUserWithProject();

        // ensure properties are used so static analysis doesn't flag them as only-written
        $this->project->getKey();
    }

    /**
     * @return array<string, array{0: string, 1: int, 2: array<string, bool|string>|null}>
     */
    public static function middlewareAccessStates(): array
    {
        $blockedResponse = self::blockedResponse();

        return [
            'subscribed user' => [self::STATE_SUBSCRIBED, 200],
            'grace period user' => [self::STATE_GRACE_PERIOD, 200],
            'trial user' => [self::STATE_TRIAL, 200],
            'post grace user' => [self::STATE_POST_GRACE, 403, $blockedResponse],
            'free user' => [self::STATE_FREE, 403, $blockedResponse],
            'expired trial user' => [self::STATE_EXPIRED_TRIAL, 403, $blockedResponse],
        ];
    }

    /**
     * @param  array<string, bool|string>|null  $expectedJson
     */
    #[Test]
    #[DataProvider('middlewareAccessStates')]
    public function subscription_states_receive_expected_middleware_response(
        string $state,
        int $expectedStatus,
        ?array $expectedJson = null,
    ): void {
        $this->applyAccessState($state);

        $response = $this->dashboardInsightsResponse();

        $response->assertStatus($expectedStatus);

        if ($expectedJson !== null) {
            $response->assertJson($expectedJson);
        }
    }

    /**
     * @return array<string, bool|string>
     */
    private static function blockedResponse(): array
    {
        return [
            'message' => 'Access denied. An active subscription is required to perform this action.',
            'error_type' => 'subscription_required',
            'upgrade_required' => true,
        ];
    }

    /**
     * @return TestResponse<\Symfony\Component\HttpFoundation\Response>
     */
    private function dashboardInsightsResponse(): TestResponse
    {
        return $this->getJson(route('dashboard.insights'));
    }

    private function applyAccessState(string $state): void
    {
        // SubscriptionHelpers maps each middleware state to the corresponding billing fixture.
        match ($state) {
            self::STATE_SUBSCRIBED => $this->createProSubscription($this->user),
            self::STATE_GRACE_PERIOD => $this->createGracePeriodSubscription($this->user),
            self::STATE_TRIAL => $this->createTrialCustomer($this->user, Carbon::now()->addDays(5)),
            self::STATE_POST_GRACE => $this->createExpiredSubscription($this->user),
            self::STATE_EXPIRED_TRIAL => $this->createTrialCustomer($this->user, Carbon::now()->subDay()),
            self::STATE_FREE => null,
            default => throw new InvalidArgumentException("Unknown access state [{$state}]."),
        };
    }
}
