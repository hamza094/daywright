<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Auth;

use App\Http\Controllers\Api\ApiController;
use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Auth\Events\Verified;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\URL;
use Illuminate\Validation\ValidationException;

class VerificationController extends ApiController
{
    /**
     * Create a new controller instance.
     */
    public function __construct()
    {
        $this->middleware('throttle:6,1')->only('resend');
    }

    /**
     * Mark the user's email address as verified.
     *
     * Confirms the signed verification request and marks the targeted user's email as verified.
     */
    public function verify(Request $request, User $user): JsonResponse
    {
        if (! URL::hasValidSignature($request)) {
            abort(400, trans('verification.invalid'));
        }

        if ($user->hasVerifiedEmail()) {
            abort(400, trans('verification.already_verified'));
        }

        $user->markEmailAsVerified();

        event(new Verified($user));

        return $this->respondWithMessage(trans('verification.verified'));
    }

    /**
     * Resend the email verification notification.
     *
     * Sends another email verification notification for the authenticated user when verification is still pending.
     */
    public function resend(Request $request): JsonResponse
    {
        $user = auth()->user();

        if (is_null($user)) {
            throw ValidationException::withMessages([
                'email' => [trans('verification.user')],
            ]);
        }

        if ($user->hasVerifiedEmail()) {
            throw ValidationException::withMessages([
                'email' => [trans('verification.already_verified')],
            ]);
        }

        $user->sendEmailVerificationNotification();

        return $this->respondWithMessage(trans('verification.sent'));
    }
}
