<?php

declare(strict_types=1);

namespace App\Services\Auth;

use App\Enums\Subscription\PlanLimitType;
use App\Models\User;
use App\Services\Subscription\PlanLimitService;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Collection;
use Laravel\Sanctum\NewAccessToken;
use Laravel\Sanctum\PersonalAccessToken;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class ApiTokenService
{
    public function __construct(private readonly PlanLimitService $planLimitService) {}

    /**
     * @return Collection<int, PersonalAccessToken>
     */
    public function listForUser(User $user): Collection
    {
        return $user->tokens;
    }

    public function createForUser(User $user, string $name, ?CarbonInterface $expiresAt): NewAccessToken
    {
        return $this->planLimitService->executeWithinAccountLimit(
            PlanLimitType::ApiTokens,
            $user,
            fn (User $lockedUser): NewAccessToken => $lockedUser->createToken(
                $name,
                ['*'],
                $expiresAt,
            )
        );
    }

    public function deleteForUser(User $user, int $tokenId): void
    {
        $currentToken = $user->currentAccessToken();

        // @phpstan-ignore-next-line - currentAccessToken() phpdoc may be overly-certain about nullability
        if ($currentToken === null) {
            throw new AccessDeniedHttpException('No current access token found.');
        }

        if ($currentToken->id === $tokenId) {
            throw new AccessDeniedHttpException('Cannot delete the current session token via this route.');
        }

        $deleted = $user->tokens()->where('id', $tokenId)->delete();

        if (! $deleted) {
            throw new NotFoundHttpException('Token not found.');
        }
    }
}
