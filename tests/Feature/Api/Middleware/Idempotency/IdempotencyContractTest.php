<?php

declare(strict_types=1);

namespace Tests\Feature\Api\Middleware\Idempotency;

use App\DataTransferObjects\Subscription\SubscriptionOperationResult;
use App\Interfaces\Paddle;
use App\Interfaces\Zoom;
use App\Models\Meeting;
use App\Models\Message;
use App\Models\SubscriptionOperation;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Contracts\Cache\Lock;
use Illuminate\Contracts\Cache\LockProvider;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Mockery;
use Mockery\MockInterface;
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
        $user = User::factory()->create();
        $this->actingAs($user);

        $headers = $this->idempotencyHeaders('phase-seven-token-create');

        $firstResponse = $this->withHeaders($headers)->postJson($this->apiV1Route('api-tokens.store'), [
            'name' => 'Phase Seven Token',
            'scopes' => ['account:read'],
        ]);

        $mismatchResponse = $this->withHeaders($headers)->postJson($this->apiV1Route('api-tokens.store'), [
            'name' => 'Different Token Name',
            'scopes' => ['account:read'],
        ]);

        $firstResponse->assertCreated();
        $this->assertDatabaseCount('personal_access_tokens', 1);

        $mismatchResponse->assertUnprocessable()
            ->assertJsonPath('message', 'Idempotency key already used with different request parameters.');
    }

    #[Test]
    public function token_creation_returns_conflict_while_the_same_key_is_in_flight(): void
    {
        $this->user = User::factory()->create();
        $this->actingAs($this->user);

        $payload = ['name' => 'Locked Token', 'scopes' => ['account:read']];
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
    public function token_creation_replays_without_creating_a_second_token(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        $headers = $this->idempotencyHeaders('phase-six-token-store');
        $payload = ['name' => 'Phase Six Token', 'scopes' => ['account:read']];

        $firstResponse = $this->withHeaders($headers)
            ->postJson($this->apiV1Route('api-tokens.store'), $payload)
            ->assertCreated();

        $secondResponse = $this->withHeaders($headers)
            ->postJson($this->apiV1Route('api-tokens.store'), $payload)
            ->assertCreated();

        $this->assertDatabaseCount('personal_access_tokens', 1);
        $this->assertSame(
            $firstResponse->json('data.token_resource.id'),
            $secondResponse->json('data.token_resource.id'),
        );
    }

    #[Test]
    public function subscription_creation_replays_without_calling_the_provider_twice(): void
    {
        $provider = new class implements Paddle
        {
            public int $subscribeCalls = 0;

            public function subscribe(User $user, string $plan): string
            {
                $this->subscribeCalls++;

                return 'https://phase-seven-paylink.test';
            }

            public function swap(User $user, string $plan, string $idempotencyKey): SubscriptionOperationResult
            {
                return new SubscriptionOperationResult(
                    operation: SubscriptionOperation::factory()->swap()->make(),
                );
            }

            public function cancel(User $user, string $plan, string $idempotencyKey): SubscriptionOperationResult
            {
                return new SubscriptionOperationResult(
                    operation: SubscriptionOperation::factory()->cancel()->make(),
                );
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
    public function invitation_send_replays_without_creating_duplicate_memberships(): void
    {
        /** @var User $invitedUser */
        $invitedUser = User::factory()->create();
        $headers = $this->idempotencyHeaders('phase-six-invitation-send');
        $payload = ['email' => $invitedUser->email];

        $this->withHeaders($headers)
            ->postJson($this->apiV1ProjectRoute('send.invitation', $this->project), $payload)
            ->assertCreated();

        $this->withHeaders($headers)
            ->postJson($this->apiV1ProjectRoute('send.invitation', $this->project), $payload)
            ->assertCreated();

        $this->assertSame(1, $this->project->members()->whereKey($invitedUser->id)->count());
        $this->assertDatabaseHas('project_members', [
            'project_id' => $this->project->id,
            'user_id' => $invitedUser->id,
            'active' => false,
        ]);
    }

    #[Test]
    public function project_message_send_replays_without_creating_duplicate_messages(): void
    {
        $this->user->forceFill(['is_admin' => true])->save();

        $headers = $this->idempotencyHeaders('phase-six-message-send');
        $payload = [
            'message' => 'Phase six message payload',
            'users' => json_encode([$this->user->id]),
            'subject' => 'Phase six subject',
            'mail' => true,
        ];

        $route = $this->apiV1ProjectRoute('projects.messages.store', $this->project);

        $this->withHeaders($headers)->postJson($route, $payload)->assertOk();
        $this->withHeaders($headers)->postJson($route, $payload)->assertOk();

        $this->assertSame(1, Message::query()->count());
        $this->assertDatabaseHas('messages', [
            'project_id' => $this->project->id,
            'subject' => 'Phase six subject',
            'type' => 'mail',
        ]);
    }

    #[Test]
    public function task_assignment_replays_without_creating_duplicate_task_members(): void
    {
        $task = $this->project->addTask('phase six task assignment');
        /** @var User $member */
        $member = User::factory()->create();
        $member->members()->syncWithoutDetaching([
            $this->project->id => ['active' => true],
        ]);

        $headers = $this->idempotencyHeaders('phase-six-task-assign');
        $route = route('api.v1.task.assign', [
            'project' => $this->project->slug,
            'task' => $task->id,
        ]);
        $payload = ['members' => [$member->id]];

        $this->withHeaders($headers)->patchJson($route, $payload)->assertOk();
        $this->withHeaders($headers)->patchJson($route, $payload)->assertOk();

        $this->assertSame(1, $task->assignee()->whereKey($member->id)->count());
        $this->assertDatabaseHas('task_user', [
            'task_id' => $task->id,
            'user_id' => $member->id,
        ]);
    }

    #[Test]
    public function task_unassign_replays_without_error_after_the_first_removal(): void
    {
        $task = $this->project->addTask('phase six task unassign');
        $task->assignee()->attach($this->user);

        $headers = $this->idempotencyHeaders('phase-six-task-unassign');
        $route = route('api.v1.task.unassign', [
            'project' => $this->project->slug,
            'task' => $task->id,
        ]);
        $payload = ['member' => $this->user->id];

        $this->withHeaders($headers)->patchJson($route, $payload)->assertOk();
        $this->withHeaders($headers)->patchJson($route, $payload)->assertOk();

        $this->assertDatabaseMissing('task_user', [
            'task_id' => $task->id,
            'user_id' => $this->user->id,
        ]);
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
    public function meeting_update_replays_without_calling_zoom_twice(): void
    {
        $meeting = Meeting::factory()
            ->for($this->project)
            ->create(['user_id' => $this->user->id]);

        $this->mock(Zoom::class, function (MockInterface $mock) use ($meeting): void {
            $mock->shouldReceive('updateMeeting')
                ->once()
                ->with(
                    Mockery::on(fn (array $payload): bool => $payload['meeting_id'] === $meeting->meeting_id && $payload['duration'] === 45),
                    Mockery::type(User::class),
                );
        });

        $headers = $this->idempotencyHeaders('phase-six-meeting-update');
        $payload = [
            'duration' => 45,
        ];
        $route = $this->apiV1Route('meetings.update', ['project' => $this->project, 'meeting' => $meeting]);

        $this->withHeaders($headers)->patchJson($route, $payload)->assertOk();
        $this->withHeaders($headers)->patchJson($route, $payload)->assertOk();

        $this->assertDatabaseHas('meetings', [
            'id' => $meeting->id,
            'meeting_id' => $meeting->meeting_id,
            'duration' => 45,
        ]);
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

        $lock = $store->lock(
            app(IdempotencyCache::class)->lockKey($storageKey),
            config()->integer('idempotency.lock_timeout'),
        );

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
}
