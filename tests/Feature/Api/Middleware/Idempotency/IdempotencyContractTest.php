<?php

declare(strict_types=1);

namespace Tests\Feature\Api\Middleware\Idempotency;

use App\Interfaces\Paddle;
use App\Jobs\Webhooks\Zoom\UpdateMeetingWebhook;
use App\Models\Meeting;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Contracts\Cache\Lock;
use Illuminate\Contracts\Cache\LockProvider;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Queue;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;
use Tests\Traits\InteractsWithZoom;
use Tests\Traits\ProjectSetup;
use WendellAdriel\Idempotency\Enums\IdempotencyScope;
use WendellAdriel\Idempotency\Support\IdempotencyCache;
use WendellAdriel\Idempotency\Support\RequestFingerprint;
use WendellAdriel\Idempotency\Support\ScopeResolver;

use function Safe\json_encode;

final class IdempotencyContractTest extends TestCase
{
    use InteractsWithZoom;
    use ProjectSetup;
    use RefreshDatabase;

    #[Test]
    public function token_creation_rejects_mismatched_payloads_after_first_execution(): void
    {
        $headers = $this->idempotencyHeaders('phase-seven-token-create');

        $firstResponse = $this->withHeaders($headers)->postJson($this->apiV1Route('api-tokens.store'), [
            'name' => 'Phase Seven Token',
        ]);

        $mismatchResponse = $this->withHeaders($headers)->postJson($this->apiV1Route('api-tokens.store'), [
            'name' => 'Different Token Name',
        ]);

        $firstResponse->assertCreated();
        $this->assertDatabaseCount('personal_access_tokens', 1);

        $mismatchResponse->assertUnprocessable()
            ->assertJsonPath('message', 'Idempotency key already used with different request parameters.');
    }

    #[Test]
    public function token_creation_returns_conflict_while_the_same_key_is_in_flight(): void
    {
        $payload = ['name' => 'Locked Token'];
        $idempotencyKey = 'phase-seven-token-in-flight';
        $lock = $this->acquireUserScopedLock(
            method: 'POST',
            url: $this->apiV1Route('api-tokens.store'),
            payload: $payload,
            clientKey: $idempotencyKey,
        );

        try {
            $this->withHeaders($this->idempotencyHeaders($idempotencyKey))
                ->postJson($this->apiV1Route('api-tokens.store'), $payload)
                ->assertConflict()
                ->assertJsonPath('message', 'A request with this idempotency key is currently being processed.');

            $this->assertDatabaseCount('personal_access_tokens', 0);
        } finally {
            $lock->release();
        }
    }

    #[Test]
    public function subscription_creation_replays_without_calling_the_provider_twice(): void
    {
        $provider = new class implements Paddle
        {
            public int $subscribeCalls = 0;

            public function subscribe(User $user, string $plan): mixed
            {
                $this->subscribeCalls++;

                return 'https://phase-seven-paylink.test';
            }

            public function swap(User $user, string $plan): array
            {
                return ['message' => 'unused'];
            }

            public function cancel(User $user, string $plan): array
            {
                return ['message' => 'unused'];
            }
        };

        $this->swap(Paddle::class, $provider);

        $headers = $this->idempotencyHeaders('phase-seven-subscription-store');
        $payload = ['plan' => 'monthly'];

        $this->withHeaders($headers)
            ->postJson($this->apiV1Route('users.me.subscription.store'), $payload)
            ->assertOk();

        $this->withHeaders($headers)
            ->postJson($this->apiV1Route('users.me.subscription.store'), $payload)
            ->assertOk();

        $this->assertSame(1, $provider->subscribeCalls);
    }

    #[Test]
    public function meeting_creation_replays_without_creating_a_second_meeting(): void
    {
        $zoomFake = $this->fakeZoom();
        $headers = $this->idempotencyHeaders('phase-seven-meeting-store');
        $payload = $this->meetingPayload();

        $this->withHeaders($headers)->postJson(
            $this->apiV1ProjectRoute('meetings.store', $this->project),
            $payload,
        )->assertSuccessful();

        $this->withHeaders($headers)->postJson(
            $this->apiV1ProjectRoute('meetings.store', $this->project),
            $payload,
        )->assertSuccessful();

        $this->assertCount(1, $zoomFake->meetingsToCreate);
        $this->assertDatabaseCount('meetings', 1);
    }

    #[Test]
    public function zoom_webhook_update_replays_without_queuing_the_job_twice(): void
    {
        config(['services.zoom.webhook_secret' => 'secret']);

        Queue::fake([
            UpdateMeetingWebhook::class,
        ]);

        Meeting::factory()->create(['meeting_id' => 813]);

        $payload = File::json(
            path: base_path('tests/Fixtures/Webhooks/Zoom/meeting_update.json'),
            flags: JSON_THROW_ON_ERROR,
        );

        $headers = $this->zoomWebhookHeaders($payload, 'phase-seven-zoom-update-813');

        $this->postJson(route('api.v1.webhooks.meetings.update'), $payload, $headers)
            ->assertOk();

        $this->postJson(route('api.v1.webhooks.meetings.update'), $payload, $headers)
            ->assertOk();

        $object = $payload['payload']['object'];
        $meetingId = $object['id'];
        $updateData = collect($object)->except(['id', 'uuid'])->toArray();

        Queue::assertPushed(UpdateMeetingWebhook::class, fn ($job): bool => $job->meeting_id === $meetingId && $job->update_data === $updateData);
        Queue::assertPushed(UpdateMeetingWebhook::class, 1);
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function acquireUserScopedLock(string $method, string $url, array $payload, string $clientKey): Lock
    {
        $request = Request::create(
            uri: $url,
            method: $method,
            server: [
                'CONTENT_TYPE' => 'application/json',
                'HTTP_ACCEPT' => 'application/json',
            ],
            content: json_encode($payload, JSON_THROW_ON_ERROR),
        );

        $route = app('router')->getRoutes()->match($request);
        $request->setRouteResolver(static fn () => $route);
        $request->setUserResolver(fn (): User => $this->user);

        [$resolvedScope, $identifier] = app(ScopeResolver::class)->describe($request, IdempotencyScope::User);
        $scopePrefix = $resolvedScope === IdempotencyScope::Global
            ? IdempotencyScope::Global->value
            : sprintf('%s:%s', $resolvedScope->value, $identifier);

        $storageKey = app(RequestFingerprint::class)->storageKey(
            request: $request,
            scopePrefix: $scopePrefix,
            header: (string) config('idempotency.header'),
            clientKey: $clientKey,
        );

        $store = cache()->getStore();

        $this->assertInstanceOf(LockProvider::class, $store);

        $lock = $store->lock(app(IdempotencyCache::class)->lockKey($storageKey), 10);

        $this->assertTrue($lock->get());

        return $lock;
    }

    /**
     * @return array<string, mixed>
     */
    private function meetingPayload(): array
    {
        return [
            'topic' => 'phase-seven-meeting',
            'agenda' => 'phase-seven-agenda',
            'duration' => 30,
            'password' => 'metingpass',
            'join_before_host' => false,
            'start_time' => Carbon::now()->addWeek()->toIso8601String(),
            'timezone' => 'UTC',
        ];
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, string>
     */
    private function zoomWebhookHeaders(array $payload, string $requestId): array
    {
        $timestamp = (string) time();

        return [
            'x-zm-request-timestamp' => $timestamp,
            'x-zm-signature' => $this->buildSignature($timestamp, $payload),
            'x-zm-request-id' => $requestId,
        ];
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function buildSignature(string $timestamp, array $payload): string
    {
        $message = 'v0:'.$timestamp.':'.json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);

        return 'v0='.hash_hmac('sha256', $message, (string) config('services.zoom.webhook_secret'));
    }
}
