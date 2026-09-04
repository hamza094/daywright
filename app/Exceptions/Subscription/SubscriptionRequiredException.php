<?php

declare(strict_types=1);

namespace App\Exceptions\Subscription;

use App\Exceptions\ApiException;
use App\Exceptions\Support\ErrorCode;
use Illuminate\Contracts\Debug\ShouldntReport;
use Illuminate\Http\Request;
use Override;
use Symfony\Component\HttpFoundation\Response;

final class SubscriptionRequiredException extends ApiException implements ShouldntReport
{
    public function __construct(
        string $message = 'Access denied. An active subscription is required to perform this action.',
        private readonly bool $upgradeRequired = true,
    ) {
        parent::__construct($message);
    }

    public function errorType(): string
    {
        return 'subscription_required';
    }

    public function upgradeRequired(): bool
    {
        return $this->upgradeRequired;
    }

    public function status(): int
    {
        return Response::HTTP_FORBIDDEN;
    }

    public function errorCode(): string
    {
        return ErrorCode::SUBSCRIPTION_REQUIRED;
    }

    /**
     * @return array<string, mixed>
     */
    #[Override]
    public function meta(Request $request): array
    {
        return [
            'upgrade_required' => $this->upgradeRequired,
        ];
    }

    #[Override]
    protected function defaultMessage(): string
    {
        return 'Access denied. An active subscription is required to perform this action.';
    }
}
