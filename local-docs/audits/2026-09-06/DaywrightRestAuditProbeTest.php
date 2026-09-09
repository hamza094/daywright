<?php

declare(strict_types=1);

// Forensic audit probes: passing assertions CONFIRM the current defects.
// These are deliberately outside tests/. Invert expectations for regression tests after fixes.
// Execute only with the repository's testing configuration and an isolated test database.

use App\DataTransferObjects\Task\TaskUpdateData;
use App\Enums\TaskSystemStatus;
use App\Http\Middleware\VerifyZoomWebhook;
use App\Models\Project;
use App\Models\Task;
use App\Models\User;
use App\Services\Task\TaskService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Queue;
use WendellAdriel\Idempotency\Http\Middleware\Idempotent;

final class DaywrightRestAuditProbeTest extends Tests\TestCase
{
    use RefreshDatabase;

    public function test_team_write_token_can_replace_verified_login_email(): void
    {
        $user = User::factory()->create(['email_verified_at' => now()]);
        $token = $user->createToken('audit-team-only', ['team:write'])->plainTextToken;
        $this->withToken($token)->patchJson(route('api.v1.users.update', $user), ['email' => 'audit-replacement@example.test'])->assertOk();
        $this->assertSame('audit-replacement@example.test', $user->fresh()->email);
        $this->assertNotNull($user->fresh()->email_verified_at);
    }

    public function test_replay_cache_suppresses_retry_after_downstream_failure(): void
    {
        config(['services.zoom.webhook_secret' => 'audit-only-secret']);
        $timestamp = (string) time();
        $body = json_encode(['event' => 'meeting.updated', 'payload' => ['object' => ['id' => 123]]]);
        $request = Request::create('/api/v1/webhooks/zoom', 'POST', [], [], [], ['CONTENT_TYPE' => 'application/json'], $body);
        $request->headers->set('x-zm-request-id', 'audit-request-1');
        $request->headers->set('x-zm-request-timestamp', $timestamp);
        $request->headers->set('x-zm-signature', 'v0='.hash_hmac('sha256', "v0:{$timestamp}:{$body}", 'audit-only-secret'));
        $middleware = app(VerifyZoomWebhook::class);
        $calls = 0;
        try {
            $middleware->handle($request, function () use (&$calls) {
                $calls++;
                throw new RuntimeException('simulated queue unavailable');
            });
            $this->fail('Expected downstream error');
        } catch (RuntimeException $e) {
            $this->assertSame('simulated queue unavailable', $e->getMessage());
        }
        $response = $middleware->handle($request, function () use (&$calls) {
            $calls++;

            return response()->json(['queued' => true]);
        });
        $this->assertSame(202, $response->getStatusCode());
        $this->assertSame(1, $calls);
    }

    public function test_same_key_on_different_projects_replays_first_invitation(): void
    {
        Notification::fake();
        $user = User::factory()->create();
        $invitee = User::factory()->create();
        $first = Project::factory()->for($user)->create();
        $second = Project::factory()->for($user)->create();
        $token = $user->createToken('audit-team-only', ['team:write'])->plainTextToken;
        $headers = ['Authorization' => 'Bearer '.$token, 'Idempotency-Key' => 'audit-same-route-different-project'];
        $payload = ['email' => $invitee->email];
        $this->postJson(route('api.v1.send.invitation', $first), $payload, $headers)->assertCreated();
        $this->postJson(route('api.v1.send.invitation', $second), $payload, $headers)->assertCreated();
        $this->assertSame(1, $first->members()->count());
        $this->assertSame(0, $second->members()->count());
    }

    public function test_stale_task_snapshot_overwrites_terminal_state(): void
    {
        Queue::fake();
        $task = Task::factory()->create(['status_id' => TaskSystemStatus::Pending->value]);
        $stale = Task::findOrFail($task->id);
        $service = app(TaskService::class);
        $service->updateTask($task, TaskUpdateData::fromArray(['status_id' => TaskSystemStatus::Cancelled->value]));
        $service->updateTask($stale, TaskUpdateData::fromArray(['status_id' => TaskSystemStatus::InProgress->value]));
        $this->assertSame(TaskSystemStatus::InProgress->value, (int) $task->fresh()->status_id);
    }

    public function test_idempotency_lock_expires_while_first_request_still_running(): void
    {
        $request = Request::create('/audit-slow-operation', 'POST', [], [], [], ['CONTENT_TYPE' => 'application/json'], '{}');
        $request->headers->set('Idempotency-Key', 'audit-slow-key');
        $middleware = app(Idempotent::class);
        $calls = 0;
        try {
            $middleware->handle($request, function () use ($request, $middleware, &$calls) {
                $calls++;
                $this->travel(11)->seconds();
                $middleware->handle($request, function () use (&$calls) {
                    $calls++;

                    return response()->json(['second' => true]);
                });

                return response()->json(['first' => true]);
            });
        } finally {
            $this->travelBack();
        }
        $this->assertSame(2, $calls);
    }

    public function test_put_preserves_omitted_fields_and_rejects_identical_retry(): void
    {
        Notification::fake();
        $user = User::factory()->create();
        $project = Project::factory()->for($user)->create();
        $token = $user->createToken('audit-projects', ['projects:write'])->plainTextToken;
        $headers = ['Authorization' => 'Bearer '.$token];
        $payload = ['notes' => 'Audit updated notes'];
        $this->putJson(route('api.v1.projects.update', $project), $payload, $headers)->assertOk();
        $this->assertSame($project->about, $project->fresh()->about);
        $this->putJson(route('api.v1.projects.update', $project), $payload, $headers)->assertUnprocessable();
    }

    public function test_project_next_link_drops_active_query_parameters(): void
    {
        $user = User::factory()->create();
        Project::factory()->count(2)->for($user)->create(['name' => 'Audit Project Match']);
        $token = $user->createToken('audit-project-read', ['projects:read'])->plainTextToken;
        $url = route('api.v1.projects.index', ['per_page' => 1, 'sort' => 'name', 'filter' => ['search' => 'Audit']]);
        $response = $this->withToken($token)->getJson($url)->assertOk();
        $next = $response->json('links.next');
        $this->assertIsString($next);
        parse_str(parse_url($next, PHP_URL_QUERY), $query);
        $this->assertSame(['page' => '2'], $query);
    }

    public function test_http_exception_formatting_drops_protocol_headers(): void
    {
        Illuminate\Support\Facades\Route::get('/api/audit-method-error', function () {
            throw new Symfony\Component\HttpKernel\Exception\MethodNotAllowedHttpException(['GET']);
        });
        Illuminate\Support\Facades\Route::get('/api/audit-retry-error', function () {
            throw new Symfony\Component\HttpKernel\Exception\HttpException(409, 'Busy', null, ['Retry-After' => '1']);
        });
        $this->getJson('/api/audit-method-error')->assertStatus(405)->assertHeaderMissing('Allow');
        $this->getJson('/api/audit-retry-error')->assertStatus(409)->assertHeaderMissing('Retry-After');
    }

    public function test_subscription_form_routes_call_missing_methods(): void
    {
        $user = User::factory()->create();
        $token = $user->createToken('audit-account-read', ['account:read'])->plainTextToken;
        $this->withToken($token)->getJson('/api/v1/users/me/subscription/edit')->assertStatus(500);
        $this->withToken($token)->getJson('/api/v1/users/me/subscription/create')->assertStatus(500);
    }

    public function test_owner_policy_allows_team_write_token_to_change_another_accounts_email(): void
    {
        $caller = User::factory()->create();
        $target = User::factory()->create(['email_verified_at' => now()]);
        $token = $caller->createToken('audit-team-only', ['team:write'])->plainTextToken;
        $this->withToken($token)->patchJson(route('api.v1.users.update', $target), ['email' => 'audit-other-account@example.test'])->assertOk();
        $this->assertSame('audit-other-account@example.test', $target->fresh()->email);
        $this->assertNotNull($target->fresh()->email_verified_at);
    }
}
