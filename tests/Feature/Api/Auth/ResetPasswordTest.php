<?php

declare(strict_types=1);

namespace Tests\Feature\Api\Auth;

use App\Models\User;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Password;
use Tests\TestCase;

class ResetPasswordTest extends TestCase
{
    use RefreshDatabase;
    /**
     * Sends the password reset email when the user exists.
     */

    /** @test */
    public function can_sends_password_reset_email(): void
    {
        $user = User::factory()->create();

        Notification::fake();

        $this->postJson($this->apiV1Route('password.email'), ['email' => $user->email]);

        Notification::assertSentTo($user, ResetPassword::class);
    }

    /**
     * Allows a user to reset their password.
     */

    /** @test */
    public function user_reset_their_password(): void
    {
        $user = User::factory()->create();

        $token = Password::createToken($user);

        $this->postJson($this->apiV1Route('password.update'), [
            'token' => $token,
            'email' => $user->email,
            'password' => 'Password#333',
            'password_confirmation' => 'Password#333',
        ])->assertSuccessful();

        $this->assertTrue(Hash::check('Password#333', $user->fresh()->password));
    }
}
