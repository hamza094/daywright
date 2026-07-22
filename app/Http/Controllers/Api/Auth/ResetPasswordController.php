<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Auth;

use App\Http\Controllers\Api\ApiController;
use App\Http\Requests\Api\V1\Auth\ForgotPasswordRequest;
use App\Http\Requests\Api\V1\Auth\ResetPasswordRequest;
use App\Models\User;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response as HttpResponse;
use Symfony\Component\HttpKernel\Exception\HttpException;

class ResetPasswordController extends ApiController
{
    /*
    |--------------------------------------------------------------------------
    | Password Reset Controller
    |--------------------------------------------------------------------------
    |
    | and uses a simple trait to include this behavior. You're free to
    | This controller is responsible for handling password reset requests
    | explore this trait and override any methods you wish to tweak.
    |
    */

    /**
     * Send a password reset link.
     *
     * Sends a password reset email when the address belongs to an existing account.
     * The response message is intentionally generic.
     *
     * @unauthenticated
     */
    public function sendResetLink(ForgotPasswordRequest $request): JsonResponse
    {
        $data = $request->toDto();

        $status = Password::sendResetLink(
            $data->toArray()
        );

        if ($status !== Password::RESET_LINK_SENT) {
            Log::warning('Password reset link request failed', [
                'status' => $status,
                'user_id' => User::whereEmail($data->email)->select('uuid')->first()?->uuid,
            ]);
        }

        return $this->respondWithMessage('If your email exists in our system, you will receive a password reset link shortly.');
    }

    /**
     * Reset a password with a valid reset token.
     *
     * Completes the password reset flow for a guest user and invalidates the reset token.
     *
     * @unauthenticated
     */
    public function resetPassword(ResetPasswordRequest $request): JsonResponse
    {
        $data = $request->toDto();

        $status = Password::reset(
            $data->toArray(),
            function (User $user, string $password): void {
                $user->forceFill([
                    'password' => Hash::make($password),
                ])->setRememberToken(Str::random(60));

                $user->save();

                event(new PasswordReset($user));
            }
        );

        if ($status === Password::PASSWORD_RESET) {
            return $this->respondWithMessage('Password reset successfully.');
        }

        Log::warning('Password reset attempt failed', [
            'status' => $status,
            'user_id' => User::whereEmail($data->email)->select('uuid')->first()?->uuid,
        ]);

        throw new HttpException(HttpResponse::HTTP_BAD_REQUEST, 'Unable to reset password with the provided information.');
    }
}
