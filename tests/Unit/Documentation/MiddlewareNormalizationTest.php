<?php

declare(strict_types=1);

namespace Tests\Unit\Documentation;

use App\Documentation\Transformers\PublicApiMiddlewareResponses;
use PHPUnit\Framework\TestCase;

/**
 * Tests for middleware normalization and parsing.
 *
 * Verifies that the structured middleware parser correctly handles:
 * - Aliases vs resolved class names
 * - Parameterized middleware
 * - Different middleware types (tokenAbility, can, throttle, idempotent)
 */
class MiddlewareNormalizationTest extends TestCase
{
    /** @test */
    public function parses_simple_middleware(): void
    {
        $result = PublicApiMiddlewareResponses::parseMiddleware('throttle');

        $this->assertSame('throttle', $result['baseName']);
        $this->assertSame([], $result['parameters']);
        $this->assertSame('throttle', $result['original']);
    }

    /** @test */
    public function parses_parameterized_middleware(): void
    {
        $result = PublicApiMiddlewareResponses::parseMiddleware('throttle:sensitive-upload');

        $this->assertSame('throttle', $result['baseName']);
        $this->assertSame(['sensitive-upload'], $result['parameters']);
        $this->assertSame('throttle:sensitive-upload', $result['original']);
    }

    /** @test */
    public function parses_multiple_parameters(): void
    {
        $result = PublicApiMiddlewareResponses::parseMiddleware('can:access,project');

        $this->assertSame('can', $result['baseName']);
        $this->assertSame(['access', 'project'], $result['parameters']);
        $this->assertSame('can:access,project', $result['original']);
    }

    /** @test */
    public function parses_namespaced_middleware(): void
    {
        $result = PublicApiMiddlewareResponses::parseMiddleware('Illuminate\Auth\Middleware\Authorize');

        $this->assertSame('Authorize', $result['baseName']);
        $this->assertSame([], $result['parameters']);
        $this->assertSame('Illuminate\Auth\Middleware\Authorize', $result['original']);
    }

    /** @test */
    public function parses_namespaced_with_parameters(): void
    {
        $result = PublicApiMiddlewareResponses::parseMiddleware('Illuminate\Auth\Middleware\Authorize:access,project');

        $this->assertSame('Authorize', $result['baseName']);
        $this->assertSame(['access', 'project'], $result['parameters']);
        $this->assertSame('Illuminate\Auth\Middleware\Authorize:access,project', $result['original']);
    }

    /** @test */
    public function recognizes_token_ability_alias(): void
    {
        $middleware = [
            ['baseName' => 'tokenAbility', 'parameters' => ['projects:read'], 'original' => 'tokenAbility:projects:read'],
        ];

        $this->assertTrue(PublicApiMiddlewareResponses::hasTokenAbilityOrPolicyMiddlewareStatic($middleware));
    }

    /** @test */
    public function recognizes_token_ability_class(): void
    {
        $middleware = [
            ['baseName' => 'CheckTokenAbilities', 'parameters' => ['projects:read'], 'original' => 'CheckTokenAbilities:projects:read'],
        ];

        $this->assertTrue(PublicApiMiddlewareResponses::hasTokenAbilityOrPolicyMiddlewareStatic($middleware));
    }

    /** @test */
    public function recognizes_can_alias(): void
    {
        $middleware = [
            ['baseName' => 'can', 'parameters' => ['access', 'project'], 'original' => 'can:access,project'],
        ];

        $this->assertTrue(PublicApiMiddlewareResponses::hasTokenAbilityOrPolicyMiddlewareStatic($middleware));
    }

    /** @test */
    public function recognizes_authorize_class(): void
    {
        $middleware = [
            ['baseName' => 'Authorize', 'parameters' => ['access', 'project'], 'original' => 'Illuminate\Auth\Middleware\Authorize:access,project'],
        ];

        $this->assertTrue(PublicApiMiddlewareResponses::hasTokenAbilityOrPolicyMiddlewareStatic($middleware));
    }

    /** @test */
    public function recognizes_throttle_middleware(): void
    {
        $middleware = [
            ['baseName' => 'throttle', 'parameters' => ['sensitive-upload'], 'original' => 'throttle:sensitive-upload'],
        ];

        $this->assertTrue(PublicApiMiddlewareResponses::hasThrottleMiddlewareStatic($middleware));
    }

    /** @test */
    public function recognizes_resolved_throttle_middleware_classes(): void
    {
        foreach (['ThrottleRequests', 'ThrottleRequestsWithRedis'] as $baseName) {
            $middleware = [
                ['baseName' => $baseName, 'parameters' => ['api'], 'original' => $baseName.':api'],
            ];

            $this->assertTrue(
                PublicApiMiddlewareResponses::hasThrottleMiddlewareStatic($middleware),
                "{$baseName} should be recognized as throttle middleware",
            );
        }
    }

    /** @test */
    public function recognizes_idempotent_class(): void
    {
        $middleware = [
            ['baseName' => 'Idempotent', 'parameters' => [], 'original' => 'WendellAdriel\Idempotency\Http\Middleware\Idempotent'],
        ];

        $this->assertTrue(PublicApiMiddlewareResponses::hasIdempotencyMiddlewareStatic($middleware));
    }

    /** @test */
    public function recognizes_idempotent_alias(): void
    {
        $middleware = [
            ['baseName' => 'idempotent', 'parameters' => [], 'original' => 'idempotent'],
        ];

        $this->assertTrue(PublicApiMiddlewareResponses::hasIdempotencyMiddlewareStatic($middleware));
    }

    /** @test */
    public function does_not_recognize_unrelated_middleware(): void
    {
        $middleware = [
            ['baseName' => 'auth', 'parameters' => [], 'original' => 'auth'],
        ];

        $this->assertFalse(PublicApiMiddlewareResponses::hasTokenAbilityOrPolicyMiddlewareStatic($middleware));
        $this->assertFalse(PublicApiMiddlewareResponses::hasThrottleMiddlewareStatic($middleware));
        $this->assertFalse(PublicApiMiddlewareResponses::hasIdempotencyMiddlewareStatic($middleware));
    }
}
