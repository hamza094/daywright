<?php

declare(strict_types=1);

namespace Tests\Feature\Api\Middleware\Idempotency;

use Closure;
use Illuminate\Cache\ArrayStore;
use Illuminate\Cache\Repository;
use Illuminate\Contracts\Cache\Lock;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\TestCase;
use WendellAdriel\Idempotency\Enums\IdempotencyScope;
use WendellAdriel\Idempotency\Http\Middleware\Idempotent;
use WendellAdriel\Idempotency\Support\IdempotencyCache;
use WendellAdriel\Idempotency\Support\IdempotencyIndex;
use WendellAdriel\Idempotency\Support\RequestFingerprint;
use WendellAdriel\Idempotency\Support\ScopeResolver;

final class CompletionRecheckTest extends TestCase
{
    public function test_expired_lock_owner_cannot_release_a_successor_lock(): void
    {
        $store = new ArrayStore;
        $oldLock = $store->lock('owner-safe-release', 10);

        $this->assertTrue($oldLock->get());

        try {
            $this->travel(11)->seconds();

            $successorLock = $store->lock('owner-safe-release', 90);
            $this->assertTrue($successorLock->get());
            $this->assertFalse($oldLock->release());

            $contender = $store->lock('owner-safe-release', 90);
            $this->assertFalse($contender->get());

            $successorLock->release();
        } finally {
            $this->travelBack();
        }
    }

    public function test_lock_remains_held_past_the_old_ten_second_boundary(): void
    {
        config(['idempotency.lock_timeout' => 90]);

        $request = Request::create(
            uri: 'https://daywright.test/idempotency/slow-operation',
            method: 'POST',
            server: [
                'CONTENT_TYPE' => 'application/json',
                'HTTP_IDEMPOTENCY_KEY' => 'slow-operation',
            ],
            content: '{}',
        );
        $middleware = app(Idempotent::class);
        $executions = 0;

        try {
            $middleware->handle(
                $request,
                function () use ($middleware, $request, &$executions): Response {
                    $executions++;
                    $this->travel(11)->seconds();

                    try {
                        $middleware->handle(
                            $request,
                            function () use (&$executions): Response {
                                $executions++;

                                return new Response('{"request":"duplicate"}');
                            },
                            scope: IdempotencyScope::Global,
                        );

                        $this->fail('The duplicate request acquired an unexpired idempotency lock.');
                    } catch (HttpException $exception) {
                        $this->assertSame(Response::HTTP_CONFLICT, $exception->getStatusCode());
                    }

                    return new Response('{"request":"original"}');
                },
                scope: IdempotencyScope::Global,
            );
        } finally {
            $this->travelBack();
        }

        $this->assertSame(1, $executions);
    }

    public function test_completed_response_is_rechecked_after_lock_acquisition(): void
    {
        config(['idempotency.lock_timeout' => 60]);

        $store = new CompletionOnAcquireArrayStore;
        $repository = new Repository($store);
        $idempotencyCache = new IdempotencyCache($repository);
        $fingerprint = new RequestFingerprint;
        $request = Request::create(
            uri: 'https://daywright.test/idempotency/recheck',
            method: 'POST',
            server: [
                'CONTENT_TYPE' => 'application/json',
                'HTTP_ACCEPT' => 'application/json',
                'HTTP_IDEMPOTENCY_KEY' => 'completion-recheck',
            ],
            content: '{"name":"example"}',
        );

        $storageKey = $fingerprint->storageKey(
            request: $request,
            scopePrefix: IdempotencyScope::Global->value,
            header: 'Idempotency-Key',
            clientKey: 'completion-recheck',
        );
        $requestFingerprint = $fingerprint->fingerprint($request);
        $storedResponse = $idempotencyCache->serializeResponse(
            new Response('{"data":{"id":123}}', Response::HTTP_CREATED, ['Content-Type' => 'application/json']),
            $requestFingerprint,
        );

        $store->afterAcquire = static function () use ($idempotencyCache, $storageKey, $storedResponse): void {
            $idempotencyCache->put($storageKey, $storedResponse, 3600);
        };

        $middleware = new Idempotent(
            $repository,
            $idempotencyCache,
            new IdempotencyIndex($repository),
            new ScopeResolver,
            $fingerprint,
        );
        $executions = 0;

        $response = $middleware->handle(
            $request,
            function () use (&$executions): Response {
                $executions++;

                return new Response('{"data":{"id":999}}', Response::HTTP_CREATED);
            },
            scope: IdempotencyScope::Global,
        );

        $this->assertSame(0, $executions);
        $this->assertSame(Response::HTTP_CREATED, $response->getStatusCode());
        $this->assertSame('{"data":{"id":123}}', $response->getContent());
        $this->assertSame('true', $response->headers->get('Idempotency-Replayed'));
        $this->assertSame(60, $store->requestedLockSeconds);
        $this->assertArrayNotHasKey($idempotencyCache->lockKey($storageKey), $store->locks);
    }

    public function test_default_lock_timeout_is_90_seconds(): void
    {
        $this->assertSame(90, config('idempotency.lock_timeout'));
    }
}

final class CompletionOnAcquireArrayStore extends ArrayStore
{
    public ?Closure $afterAcquire = null;

    public int $requestedLockSeconds = 0;

    public function lock($name, $seconds = 0, $owner = null)
    {
        $this->requestedLockSeconds = (int) $seconds;

        return new CompletionOnAcquireLock(
            parent::lock($name, $seconds, $owner),
            $this->afterAcquire,
        );
    }
}

final readonly class CompletionOnAcquireLock implements Lock
{
    public function __construct(
        private Lock $lock,
        private ?Closure $afterAcquire,
    ) {}

    public function get($callback = null)
    {
        if (! $this->lock->get()) {
            return false;
        }

        ($this->afterAcquire)?->__invoke();

        return is_callable($callback) ? $callback() : true;
    }

    public function block($seconds, $callback = null)
    {
        return $this->lock->block($seconds, $callback);
    }

    public function release(): bool
    {
        return $this->lock->release();
    }

    public function owner(): string
    {
        return $this->lock->owner();
    }

    public function forceRelease(): void
    {
        $this->lock->forceRelease();
    }
}
