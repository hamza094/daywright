<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Auth;

use App\Enums\TwoFactorStatus;
use App\Http\Controllers\Api\ApiController;
use App\Http\Requests\Api\V1\Auth\ConfirmTwoFactorRequest;
use App\Http\Requests\Api\V1\Auth\DisableTwoFactorRequest;
use App\Http\Requests\Api\V1\Auth\PrepareTwoFactorRequest;
use App\Http\Requests\Api\V1\Auth\TwoFactorLoginRequest;
use App\Services\Api\V1\Auth\LoginUserService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Two-Factor Authentication Controller
 *
 * Handles all 2FA operations including setup, confirmation, login, and management.
 */
class TwoFactorController extends ApiController
{
    public function __construct(protected LoginUserService $loginUserService) {}

    /**
     * Get the current 2FA status for the authenticated user
     */
    public function getUserStatus(Request $request): JsonResponse
    {
        $user = $request->user();

        if ($user->hasTwoFactorEnabled()) {
            return $this->respondWithData([
                'two_factor_state' => TwoFactorStatus::ENABLED->value,
            ]);
        }

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
     */
    public function prepareTwoFactor(PrepareTwoFactorRequest $request): JsonResponse
    {
        $user = $request->user();
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
     */
    public function confirmTwoFactor(ConfirmTwoFactorRequest $request): JsonResponse
    {
        $user = $request->user();

        return $this->respondWithData([
            'recovery_codes' => $user->getRecoveryCodes(),
            'two_factor_state' => TwoFactorStatus::ENABLED->value,
        ]);
    }

    /**
     * Complete 2FA login with verification code
     */
    public function twoFactorLogin(TwoFactorLoginRequest $request): JsonResponse
    {
        $user = $request->user();

        $this->loginUserService->dispatchTimezoneIfNeeded($user);

        $payload = $this->loginUserService->performSessionLogin($user, $request);

        return $this->respondWithData($payload->toArray());

    }

    /**
     * Show and regenerate recovery codes
     */
    public function showRecoveryCodes(Request $request): JsonResponse
    {
        $recoveryCodes = $request->user()->generateRecoveryCodes();

        return $this->respondWithData([
            'recovery_codes' => $recoveryCodes,
        ]);
    }

    /**
     * Disable 2FA for the authenticated user
     */
    public function disableTwoFactorAuth(DisableTwoFactorRequest $request): JsonResponse
    {
        $request->user()->disableTwoFactorAuth();

        return $this->respondWithData([
            'two_factor_state' => TwoFactorStatus::DISABLED->value,
        ]);
    }
}
