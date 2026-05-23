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

        $url = $this->verificationUrl($user);

        Event::fake();

        $this->postJson($url)
            ->assertSuccessful()
            ->assertJsonPath('data.verified', true)
            ->assertJsonMissingPath('status');

        Event::assertDispatched(Verified::class, fn (Verified $e) => $e->user->is($user));
    }

    /** @test */
    public function can_not_verify_if_signature_is_invalid(): void
    {
        $user = User::factory()->create([
            'email_verified_at' => null,
        ]);

        Sanctum::actingAs(
            $user
        );

        $this->postJson(route('api.v1.verification.verify', ['user' => $user]))
            ->assertStatus(400)
            ->assertJsonPath('message', 'verification.invalid')
            ->assertJsonPath('code', 'bad_request');
    }

    /** @test */
    public function can_not_verify_if_already_verified(): void
    {
        $user = User::factory()->create();

        Sanctum::actingAs(
            $user
        );

        $url = $this->verificationUrl($user);

        $this->postJson($url)
            ->assertStatus(400)
            ->assertJsonPath('message', 'verification.already_verified')
            ->assertJsonPath('code', 'bad_request');
    }

    /** @test */
    public function can_not_verify_another_authenticated_users_email(): void
    {
        $user = User::factory()->create([
            'email_verified_at' => null,
        ]);
        $otherUser = User::factory()->create();

        Sanctum::actingAs($otherUser);

        $this->postJson($this->verificationUrl($user))
            ->assertStatus(400)
            ->assertJsonPath('message', 'verification.invalid')
            ->assertJsonPath('code', 'bad_request');

        $this->assertNull($user->fresh()->email_verified_at);
    }

    /** @test */
    public function can_not_verify_with_a_stale_email_hash(): void
    {
        $user = User::factory()->create([
            'email_verified_at' => null,
        ]);

        $url = $this->verificationUrl($user);

        $user->update([
            'email' => 'updated-'.$user->email,
        ]);

        Sanctum::actingAs($user->fresh());

        $this->postJson($url)
            ->assertStatus(400)
            ->assertJsonPath('message', 'verification.invalid')
            ->assertJsonPath('code', 'bad_request');

        $this->assertNull($user->fresh()->email_verified_at);
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

        $this->postJson($this->apiV1Route('verification.resend'), ['email' => $user->email])
            ->assertSuccessful()
            ->assertJsonPath('message', 'verification.sent');

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

        $this->postJson($this->apiV1Route('verification.resend'), ['email' => $user->email])
            ->assertUnprocessable()
            ->assertJsonPath('message', 'Validation failed.')
            ->assertJsonPath('code', 'validation_error')
            ->assertJsonFragment([
                'errors' => [
                    'email' => ['verification.already_verified'],
                ],
            ]);

        Notification::assertNotSentTo($user, VerifyEmail::class);
    }

    /** @test */
    public function resend_verification_route_does_not_accept_a_user_path_parameter(): void
    {
        $user = User::factory()->create(['email_verified_at' => null]);

        Sanctum::actingAs($user);

        $this->postJson("/api/v1/email/resend/{$user->uuid}")
            ->assertStatus(405)
            ->assertJsonPath('message', 'Method not allowed.')
            ->assertJsonPath('code', 'method_not_allowed');
    }

    private function verificationUrl(User $user): string
    {
        return URL::temporarySignedRoute('api.v1.verification.verify', now()->addMinutes(60), [
            'user' => $user->uuid,
            'hash' => sha1((string) $user->getEmailForVerification()),
        ]);
    }
}
