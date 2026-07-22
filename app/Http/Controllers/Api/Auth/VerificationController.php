<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Auth;

use App\Http\Controllers\Api\ApiController;
use App\Http\Requests\Api\V1\Auth\VerificationRequest;
use App\Models\User;
use App\Services\Auth\VerifyEmailService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class VerificationController extends ApiController
{
    /**
     * Create a new controller instance.
     */
    public function __construct(private readonly VerifyEmailService $verifyEmailService)
    {
        $this->middleware('throttle:6,1')->only('resend');
    }

    /**
     * Mark the user's email address as verified.
     *
     * Confirms the signed verification request and marks the targeted user's email as verified.
     */
    public function verify(VerificationRequest $request, User $user): JsonResponse
    {
        $data = $request->toDto();

        $verified = $this->verifyEmailService->verify(
            user: $user,
            authenticatedUser: $this->authenticatedUser(),
            hasValidSignature: $data->hasValidSignature,
            hash: $data->hash,
        );

        return $this->respondWithData([
            'verified' => $verified,
        ]);
    }

    /**
     * Resend the email verification notification.
     *
     * Sends another email verification notification for the authenticated user when verification is still pending.
     */
    public function resend(Request $request): JsonResponse
    {
        $this->verifyEmailService->resend($request->user());

        return $this->respondWithMessage(trans('verification.sent'));
    }
}
