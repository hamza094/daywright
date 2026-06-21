<?php

declare(strict_types=1);

namespace Tests\Unit\Exceptions\Paddle;

use App\Exceptions\Paddle\SubscriptionException;
use Illuminate\Http\Request;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

final class SubscriptionExceptionTest extends TestCase
{
    #[Test]
    public function it_includes_action_in_context(): void
    {
        $exception = new SubscriptionException(
            'Test message',
            action: 'subscribe'
        );

        $context = $exception->context();

        $this->assertArrayHasKey('action', $context);
        $this->assertSame('subscribe', $context['action']);
    }

    #[Test]
    public function it_includes_plan_in_context(): void
    {
        $exception = new SubscriptionException(
            'Test message',
            plan: 'monthly'
        );

        $context = $exception->context();

        $this->assertArrayHasKey('plan', $context);
        $this->assertSame('monthly', $context['plan']);
    }

    #[Test]
    public function it_includes_current_state_in_context(): void
    {
        $exception = new SubscriptionException(
            'Test message',
            currentState: 'active'
        );

        $context = $exception->context();

        $this->assertArrayHasKey('current_state', $context);
        $this->assertSame('active', $context['current_state']);
    }

    #[Test]
    public function it_filters_null_values_from_context(): void
    {
        $exception = new SubscriptionException(
            'Test message',
            action: null,
            plan: null,
            currentState: null
        );

        $context = $exception->context();

        $this->assertEmpty($context);
    }

    #[Test]
    public function it_includes_all_context_fields_when_provided(): void
    {
        $exception = new SubscriptionException(
            'Test message',
            action: 'swap',
            plan: 'yearly',
            currentState: 'trialing'
        );

        $context = $exception->context();

        $this->assertCount(3, $context);
        $this->assertSame('swap', $context['action']);
        $this->assertSame('yearly', $context['plan']);
        $this->assertSame('trialing', $context['current_state']);
    }

    #[Test]
    public function it_returns_context_in_meta(): void
    {
        $exception = new SubscriptionException(
            'Test message',
            action: 'swap',
            plan: 'yearly',
            currentState: 'active'
        );

        $request = Request::create('/api/v1/test');
        $meta = $exception->meta($request);

        $this->assertSame($exception->context(), $meta);
    }

    #[Test]
    public function it_returns_correct_status_code(): void
    {
        $exception = new SubscriptionException('Test message');

        $this->assertSame(409, $exception->status());
    }

    #[Test]
    public function it_returns_correct_error_code(): void
    {
        $exception = new SubscriptionException('Test message');

        $this->assertSame('subscription_conflict', $exception->errorCode());
    }

    #[Test]
    public function it_returns_default_message_when_none_provided(): void
    {
        $exception = new SubscriptionException(message: '');

        $this->assertSame('Subscription request could not be completed.', $exception->getMessage());
    }

    #[Test]
    public function it_returns_custom_message_when_provided(): void
    {
        $exception = new SubscriptionException('Custom error message');

        $this->assertSame('Custom error message', $exception->getMessage());
    }
}
