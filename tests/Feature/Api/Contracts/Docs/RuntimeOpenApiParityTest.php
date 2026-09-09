<?php

declare(strict_types=1);

namespace Tests\Feature\Api\Contracts\Docs;

use App\Documentation\Transformers\PublicApiMiddlewareResponses;
use Dedoc\Scramble\Scramble;
use Illuminate\Routing\Route;
use Illuminate\Routing\Router;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * Verifies that the generated public OpenAPI document describes the routes and
 * middleware contracts Laravel actually registers at runtime.
 */
final class RuntimeOpenApiParityTest extends TestCase
{
    /** @var list<string> */
    private const array DOCUMENTED_METHODS = ['get', 'post', 'put', 'patch', 'delete'];

    public function test_every_released_runtime_operation_is_documented_with_its_registered_method(): void
    {
        $docs = $this->docs();

        foreach ($this->publicRoutes() as $route) {
            $path = '/'.Str::after($route->uri(), 'api/');

            foreach ($route->methods() as $method) {
                $method = mb_strtolower((string) $method);

                if (! in_array($method, self::DOCUMENTED_METHODS, true)) {
                    continue;
                }

                $this->assertArrayHasKey($path, $docs['paths'], "{$method} {$path} should be documented");
                $this->assertArrayHasKey($method, $docs['paths'][$path], "{$method} {$path} should use its registered runtime method");
            }
        }

        foreach ($this->documentedOperations($docs) as [$path, $method]) {
            $route = $this->routeForOperation($path, $method);

            $this->assertInstanceOf(Route::class, $route, "{$method} {$path} should resolve to a runtime route");
            $this->assertContains(mb_strtoupper($method), $route->methods());
        }
    }

    public function test_every_documented_operation_matches_its_runtime_middleware_contract(): void
    {
        $docs = $this->docs();
        $this->assertSame([['http' => []]], $docs['security'] ?? []);

        foreach ($this->documentedOperations($docs) as [$path, $method, $operation]) {
            $route = $this->routeForOperation($path, $method);
            $this->assertInstanceOf(Route::class, $route, "{$method} {$path} should resolve to a runtime route");

            $middleware = array_map(
                [PublicApiMiddlewareResponses::class, 'parseMiddleware'],
                app(Router::class)->gatherRouteMiddleware($route),
            );
            $responses = $operation['responses'] ?? [];

            if ($this->requiresSanctumAuthentication($middleware)) {
                $security = array_key_exists('security', $operation)
                    ? $operation['security']
                    : ($docs['security'] ?? []);

                $this->assertSame([['http' => []]], $security, "{$method} {$path} should require bearer authentication");
                $this->assertArrayHasKey('401', $responses, "{$method} {$path} should document its authentication failure");
            } else {
                $this->assertArrayHasKey('security', $operation, "{$method} {$path} should explicitly opt out of global bearer authentication");
                $this->assertSame([], $operation['security']);
            }

            if (PublicApiMiddlewareResponses::hasTokenAbilityOrPolicyMiddlewareStatic($middleware)) {
                $this->assertArrayHasKey('403', $responses, "{$method} {$path} should document authorization failure");
            }

            if (PublicApiMiddlewareResponses::hasThrottleMiddlewareStatic($middleware)) {
                $response = $responses['429'] ?? null;
                $this->assertIsArray($response, "{$method} {$path} should document throttling");
                $this->assertArrayHasKey('Retry-After', $response['headers'] ?? [], "{$method} {$path} 429 should include Retry-After");
            }

            if (PublicApiMiddlewareResponses::hasIdempotencyMiddlewareStatic($middleware)) {
                $this->assertIdempotencyContract($operation, $method, $path);
            }
        }
    }

    /**
     * @param  array<string, mixed>  $operation
     */
    private function assertIdempotencyContract(array $operation, string $method, string $path): void
    {
        $idempotencyParameter = null;

        foreach ($operation['parameters'] ?? [] as $parameter) {
            if (($parameter['in'] ?? null) === 'header' && ($parameter['name'] ?? null) === 'Idempotency-Key') {
                $idempotencyParameter = $parameter;
                break;
            }
        }

        $this->assertIsArray($idempotencyParameter, "{$method} {$path} should document Idempotency-Key");
        $this->assertTrue($idempotencyParameter['required'] ?? false, "{$method} {$path} should require Idempotency-Key");

        $responses = $operation['responses'] ?? [];

        foreach (['400', '409', '422'] as $status) {
            $this->assertArrayHasKey($status, $responses, "{$method} {$path} should document idempotency {$status}");
        }

        $this->assertArrayHasKey('Retry-After', $responses['409']['headers'] ?? [], "{$method} {$path} idempotency conflict should include Retry-After");

        $hasReplayHeader = false;

        foreach ($responses as $status => $response) {
            if (is_numeric($status) && (int) $status >= 200 && (int) $status < 300
                && isset($response['headers']['Idempotency-Replayed'])) {
                $hasReplayHeader = true;
                break;
            }
        }

        $this->assertTrue($hasReplayHeader, "{$method} {$path} success response should document Idempotency-Replayed");
    }

    /**
     * @param  array<int, array{baseName: string, parameters: array<int, string>, original: string}>  $middleware
     */
    private function requiresSanctumAuthentication(array $middleware): bool
    {
        foreach ($middleware as $item) {
            if ($item['baseName'] === 'Authenticate' && str_contains($item['original'], 'sanctum')) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param  array<string, mixed>  $docs
     * @return list<array{0: string, 1: string, 2: array<string, mixed>}>
     */
    private function documentedOperations(array $docs): array
    {
        $operations = [];

        foreach ($docs['paths'] ?? [] as $path => $pathItem) {
            foreach (self::DOCUMENTED_METHODS as $method) {
                if (isset($pathItem[$method])) {
                    $operations[] = [$path, $method, $pathItem[$method]];
                }
            }
        }

        return $operations;
    }

    /** @return list<Route> */
    private function publicRoutes(): array
    {
        $resolver = Scramble::getGeneratorConfig(Scramble::DEFAULT_API)->routes();

        return array_values(array_filter(
            app(Router::class)->getRoutes()->getRoutes(),
            static fn (Route $route): bool => $resolver($route),
        ));
    }

    private function routeForOperation(string $path, string $method): ?Route
    {
        foreach ($this->publicRoutes() as $route) {
            if ('/'.Str::after($route->uri(), 'api/') === $path
                && in_array(mb_strtoupper($method), $route->methods(), true)) {
                return $route;
            }
        }

        return null;
    }

    /** @return array<string, mixed> */
    private function docs(): array
    {
        Gate::define('viewApiDocs', static fn (mixed $user = null): bool => true);

        $response = $this->getJson('/docs/api.json');

        $response->assertOk();

        $docs = $response->json();

        $this->assertIsArray($docs);

        return $docs;
    }
}
