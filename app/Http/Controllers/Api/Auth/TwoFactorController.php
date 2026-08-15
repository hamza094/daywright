<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Auth;

use App\Actions\Auth\DisableTwoFactorAction;
use App\Actions\Auth\EnableTwoFactorAction;
use App\Enums\TwoFactorStatus;
use App\Http\Controllers\Api\ApiController;
use App\Http\Requests\Api\V1\Auth\ConfirmTwoFactorRequest;
use App\Http\Requests\Api\V1\Auth\DisableTwoFactorRequest;
use App\Http\Requests\Api\V1\Auth\PrepareTwoFactorRequest;
use App\Http\Requests\Api\V1\Auth\RecoveryCodesRequest;
use App\Http\Requests\Api\V1\Auth\TwoFactorLoginRequest;
use App\Http\Resources\Api\V1\Auth\AuthenticatedSessionResource;
use App\Services\Auth\LoginUserService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Two-Factor Authentication Controller
 *
 * Handles all 2FA operations including setup, confirmation, login, and management.
 */
class TwoFactorController extends ApiController
{
    public function __construct(
        protected LoginUserService $loginUserService,
        private readonly EnableTwoFactorAction $enableTwoFactorAction,
        private readonly DisableTwoFactorAction $disableTwoFactorAction
    ) {}

    /**
     * Get the current 2FA status for the authenticated user
     *
     * Returns the current two-factor state and any in-progress setup details for the signed-in user.
     */
    public function getUserStatus(Request $request): JsonResponse
    {
        $user = $request->user();

        if ($user->hasTwoFactorEnabled()) {
            return $this->respondWithData([
                'two_factor_state' => TwoFactorStatus::ENABLED->value,
            ]);
        }

        /** @var \Laragear\TwoFactor\Contracts\TwoFactorTotp|null $pending */
        $pending = $user->twoFactorAuth()->whereNull('enabled_at')->first();

        if ($pending) {
            return $this->respondWithData([
                'qr_code' => $pending->toQr(),
                'uri' => $pending->toUri(),
                'string' => $pending->toString(),
                'two_factor_state' => TwoFactorStatus::IN_PROGRESS->value,
            ]);
        }

        return $this->respondWithData([
            'two_factor_state' => TwoFactorStatus::DISABLED->value,
        ]);
    }

    /**
     * Prepare 2FA setup by creating a new secret
     *
     * Starts two-factor setup and returns the QR code, URI, and plain-text secret needed by an authenticator app.
     */
    public function prepareTwoFactor(PrepareTwoFactorRequest $request): JsonResponse
    {
        $user = $request->user();
        /** @var \Laragear\TwoFactor\Contracts\TwoFactorTotp|null $secret */
        $secret = $user->createTwoFactorAuth();

        return $this->respondWithData([
            'qr_code' => $secret->toQr(),
            'uri' => $secret->toUri(),
            'string' => $secret->toString(),
            'two_factor_state' => TwoFactorStatus::IN_PROGRESS->value,
        ]);
    }

    /**
     * Confirm 2FA setup with verification code
     *
     * Verifies the submitted authenticator code and enables two-factor authentication for the user.
     */
    public function confirmTwoFactor(ConfirmTwoFactorRequest $request): JsonResponse
    {
        $user = $request->user();
        $data = $request->toDto();

        $this->enableTwoFactorAction->execute($user, $data->code);

        return $this->respondWithData([
            'recovery_codes' => $user->getRecoveryCodes(),
            'two_factor_state' => TwoFactorStatus::ENABLED->value,
        ]);
    }

    /**
     * Complete sign-in with a two-factor code.
     *
     * Finishes the browser-based login flow after the client receives a two-factor challenge.
     *
     * @unauthenticated
     */
    public function twoFactorLogin(TwoFactorLoginRequest $request): JsonResponse
    {
        $user = $request->user();
        $request->toDto();

        $this->loginUserService->dispatchTimezoneIfNeeded($user);

        $payload = $this->loginUserService->performSessionLogin($user, $request);

        return $this->respondWithData(new AuthenticatedSessionResource($payload->user));

    }

    /**
     * Generate and return fresh recovery codes
     *
     * Generates and returns a fresh set of recovery codes for the authenticated user.
     */
    public function generateRecoveryCodes(RecoveryCodesRequest $request): JsonResponse
    {
        $user = $request->user();
        $request->toDto();

        $recoveryCodes = $user->generateRecoveryCodes();

        return $this->respondWithData([
            'recovery_codes' => $recoveryCodes,
        ]);
    }

    /**
     * Disable 2FA for the authenticated user
     *
     * Turns off two-factor authentication and clears the current user's stored two-factor setup.
     */
    public function disableTwoFactorAuth(DisableTwoFactorRequest $request): JsonResponse
    {
        $user = $request->user();
        $data = $request->toDto();

        $this->disableTwoFactorAction->execute($user, $data);

        return $this->respondWithData([
            'two_factor_state' => TwoFactorStatus::DISABLED->value,
        ]);
    }
}
