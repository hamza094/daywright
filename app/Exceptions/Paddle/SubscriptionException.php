<?php

declare(strict_types=1);

namespace App\Exceptions\Paddle;

use App\Exceptions\ApiException;
use Illuminate\Contracts\Debug\ShouldntReport;
use Illuminate\Http\Request;
use Override;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

class SubscriptionException extends ApiException implements ShouldntReport
{
    public function __construct(
        string $message = '',
        private readonly ?string $action = null,
        private readonly ?string $plan = null,
        private readonly ?string $currentState = null,
        int $code = 0,
        ?Throwable $previous = null
    ) {
        if ($message === '') {
            $message = $this->defaultMessage();
        }

        parent::__construct($message, $code, $previous);
    }

    public function status(): int
    {
        return Response::HTTP_CONFLICT;
    }

    public function errorCode(): string
    {
        return 'subscription_conflict';
    }

    /** @return array<string, string|null> */
    public function context(): array
    {
        return array_filter([
            'action' => $this->action,
            'plan' => $this->plan,
            'current_state' => $this->currentState,
        ], fn (?string $value): bool => $value !== null);
    }

    #[Override]
    public function meta(Request $request): array
    {
        // Exclude current_state from public API response to prevent information leakage
        // Internal billing state should only be logged, not exposed to consumers
        return array_filter([
            'action' => $this->action,
            'plan' => $this->plan,
        ], fn (?string $value): bool => $value !== null);
    }

    #[Override]
    protected function defaultMessage(): string
    {
        return 'Subscription request could not be completed.';
    }
}
