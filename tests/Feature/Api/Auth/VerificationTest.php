<?php

declare(strict_types=1);

namespace Tests\Feature\Api\Auth;

use App\Models\User;
use Illuminate\Auth\Events\Verified;
use Illuminate\Auth\Notifications\VerifyEmail;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\URL;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class VerificationTest extends TestCase
{
    use RefreshDatabase;

    /** @test */
    public function can_verify_email(): void
    {

        $user = User::factory()->create([
            'email_verified_at' => null,
        ]);

        Sanctum::actingAs(
            $user
        );

        $url = URL::temporarySignedRoute('api.v1.verification.verify', now()->addMinutes(60), ['user' => $user->uuid]);

        Event::fake();

        $this->postJson($url)
            ->assertSuccessful()
            ->assertJsonPath('message', 'verification.verified');

        Event::assertDispatched(Verified::class, fn (Verified $e) => $e->user->is($user));
    }

    /** @test */
    public function can_not_verify_if_already_verified(): void
    {
        $user = User::factory()->create();

        Sanctum::actingAs(
            $user
        );

        $url = URL::temporarySignedRoute('api.v1.verification.verify', now()->addMinutes(60), ['user' => $user->uuid]);

        $this->postJson($url)
            ->assertStatus(400)
            ->assertJsonPath('message', 'verification.already_verified');
    }

    /** @test */
    public function can_resend_verification_notification(): void
    {
        $now = now()->startOfSecond();
        $this->travelTo($now);

        $user = User::factory()->create(['email_verified_at' => null]);

        Sanctum::actingAs(
            $user,
        );

        Notification::fake();

        $this->postJson($this->apiV1Route('verification.resend', ['user' => $user]), ['email' => $user->email])
            ->assertSuccessful();

        $expectedUrl = URL::temporarySignedRoute('api.v1.verification.verify', $now->copy()->addMinutes(60), [
            'user' => $user->uuid,
            'hash' => sha1((string) $user->getEmailForVerification()),
        ]);

        Notification::assertSentTo($user, VerifyEmail::class, fn (VerifyEmail $notification): bool => $notification->toMail($user)->actionUrl === $expectedUrl);

        $this->travelBack();
    }

    /** @test */
    public function can_not_resend_verification_notification_if_email_already_verified(): void
    {
        $user = User::factory()->create();

        Sanctum::actingAs(
            $user,
        );

        Notification::fake();

        $this->postJson($this->apiV1Route('verification.resend', ['user' => $user]), ['email' => $user->email])
            ->assertUnprocessable()
            ->assertJsonFragment([
                'errors' => [
                    'email' => ['verification.already_verified'],
                ],
            ]);

        Notification::assertNotSentTo($user, VerifyEmail::class);
    }
}
