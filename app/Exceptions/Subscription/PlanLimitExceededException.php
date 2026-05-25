<?php

declare(strict_types=1);

namespace App\Exceptions\Subscription;

use App\Exceptions\ApiException;
use Illuminate\Contracts\Debug\ShouldntReport;
use Illuminate\Http\Request;
use Override;
use Symfony\Component\HttpFoundation\Response;

final class PlanLimitExceededException extends ApiException implements ShouldntReport
{
    public const string REASON_LIMIT_REACHED = 'limit_reached';

    public const string REASON_TRIAL_EXPIRED = 'trial_expired';

    public const string SCOPE_ACCOUNT = 'account';

    public const string SCOPE_PROJECT = 'project';

    public function __construct(
        string $message,
        private readonly string $limitType,
        private readonly string $limitLabel,
        private readonly string $reason,
        private readonly int $currentUsage,
        private readonly ?int $maxAllowed,
        private readonly string $limitScope,
        private readonly int $limitOwnerId,
    ) {
        parent::__construct($message);
    }

    public function limitType(): string
    {
        return $this->limitType;
    }

    public function reason(): string
    {
        return $this->reason;
    }

    public function limitLabel(): string
    {
        return $this->limitLabel;
    }

    public function currentUsage(): int
    {
        return $this->currentUsage;
    }

    public function maxAllowed(): ?int
    {
        return $this->maxAllowed;
    }

    public function limitScope(): string
    {
        return $this->limitScope;
    }

    public function limitOwnerId(): int
    {
        return $this->limitOwnerId;
    }

    public function status(): int
    {
        return Response::HTTP_FORBIDDEN;
    }

    public function errorCode(): string
    {
        return 'plan_limit_exceeded';
    }

    /**
     * @return array<string, mixed>
     */
    #[Override]
    public function meta(Request $request): array
    {
        $authenticatedUser = $request->user();
        $canUpgrade = $this->limitScope === self::SCOPE_ACCOUNT
            || (int) ($authenticatedUser?->getKey() ?? 0) === $this->limitOwnerId;

        return [
            'reason' => $this->reason,
            'limit_type' => $this->limitType,
            'limit_label' => $this->limitLabel,
            'current_usage' => $this->currentUsage,
            'max_allowed' => $this->maxAllowed,
            'limit_scope' => $this->limitScope,
            'can_upgrade' => $canUpgrade,
            'upgrade_required' => true,
        ];
    }

    #[Override]
    protected function defaultMessage(): string
    {
        return 'Plan limit exceeded.';
    }
}
