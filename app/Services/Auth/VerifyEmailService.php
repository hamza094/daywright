<?php

declare(strict_types=1);

namespace App\Services\Auth;

use App\Models\User;
use Illuminate\Auth\Events\Verified;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\HttpException;

final class VerifyEmailService
{
    public function verify(User $user, User $authenticatedUser, bool $hasValidSignature, string $hash): bool
    {
        if (! $hasValidSignature
            || ! $authenticatedUser->is($user)
            || ! hash_equals(sha1((string) $user->getEmailForVerification()), $hash)) {
            throw new HttpException(Response::HTTP_BAD_REQUEST, trans('verification.invalid'));
        }

        if ($user->hasVerifiedEmail()) {
            throw new HttpException(Response::HTTP_BAD_REQUEST, trans('verification.already_verified'));
        }

        $user->markEmailAsVerified();

        event(new Verified($user));

        return true;
    }

    public function resend(?User $user): void
    {
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
    }
}
