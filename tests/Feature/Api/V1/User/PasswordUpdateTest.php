<?php

declare(strict_types=1);

namespace Tests\Feature\Api\V1\User;

use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class PasswordUpdateTest extends TestCase
{
    use RefreshDatabase;

    /** @test */
    public function authenticated_user_can_change_password_via_web_session(): void
    {
        $user = User::factory()->create(['password' => Hash::make('OldPassword123!')]);

        $this->actingAs($user, 'web')
            ->putJson('/api/v1/users/me/password', [
                'current_password' => 'OldPassword123!',
                'password' => 'NewPassword456!',
                'password_confirmation' => 'NewPassword456!',
            ])
            ->assertSuccessful()
            ->assertJson(['message' => 'Password updated successfully.']);

        $this->assertTrue(Hash::check('NewPassword456!', $user->fresh()->password));
    }

    /** @test */
    public function authenticated_user_can_change_password_via_mobile_wildcard_token(): void
    {
        $user = User::factory()->create(['password' => Hash::make('OldPassword123!')]);
        $token = $user->createToken('Mobile App', ['*'])->plainTextToken;

        $this->withToken($token)
            ->putJson('/api/v1/users/me/password', [
                'current_password' => 'OldPassword123!',
                'password' => 'NewPassword456!',
                'password_confirmation' => 'NewPassword456!',
            ])
            ->assertSuccessful()
            ->assertJson(['message' => 'Password updated successfully.']);

        $this->assertTrue(Hash::check('NewPassword456!', $user->fresh()->password));
    }

    /** @test */
    public function third_party_api_token_cannot_change_password(): void
    {
        $user = User::factory()->create(['password' => Hash::make('OldPassword123!')]);
        $token = $user->createToken('Third Party', ['projects:read', 'team:write'])->plainTextToken;

        $this->withToken($token)
            ->putJson('/api/v1/users/me/password', [
                'current_password' => 'OldPassword123!',
                'password' => 'NewPassword456!',
                'password_confirmation' => 'NewPassword456!',
            ])
            ->assertStatus(403)
            ->assertJson([
                'message' => 'This operation is restricted to first-party clients only. Web sessions and official mobile apps are allowed.',
                'code' => 'forbidden',
            ]);

        $this->assertTrue(Hash::check('OldPassword123!', $user->fresh()->password));
    }

    /** @test */
    public function password_change_requires_current_password_validation(): void
    {
        $user = User::factory()->create(['password' => Hash::make('OldPassword123!')]);

        $this->actingAs($user, 'web')
            ->putJson('/api/v1/users/me/password', [
                'current_password' => 'WrongPassword123!',
                'password' => 'NewPassword456!',
                'password_confirmation' => 'NewPassword456!',
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['current_password']);
    }

    /** @test */
    public function password_change_requires_password_confirmation(): void
    {
        $user = User::factory()->create(['password' => Hash::make('OldPassword123!')]);

        $this->actingAs($user, 'web')
            ->putJson('/api/v1/users/me/password', [
                'current_password' => 'OldPassword123!',
                'password' => 'NewPassword456!',
                'password_confirmation' => 'DifferentPassword456!',
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['password']);
    }

    /** @test */
    public function unauthenticated_user_cannot_change_password(): void
    {
        $this->putJson('/api/v1/users/me/password', [
            'current_password' => 'OldPassword123!',
            'password' => 'NewPassword456!',
            'password_confirmation' => 'NewPassword456!',
        ])
            ->assertStatus(401)
            ->assertJson([
                'message' => 'Authentication is required.',
                'code' => 'unauthenticated',
            ]);
    }

    /** @test */
    public function password_change_creates_audit_log(): void
    {
        $user = User::factory()->create(['password' => Hash::make('OldPassword123!')]);

        $this->actingAs($user, 'web')
            ->putJson('/api/v1/users/me/password', [
                'current_password' => 'OldPassword123!',
                'password' => 'NewPassword456!',
                'password_confirmation' => 'NewPassword456!',
            ])
            ->assertSuccessful();

        $this->assertDatabaseHas('audit_logs', [
            'actor_type' => 'user',
            'actor_id' => $user->id,
            'event' => 'security.password_changed',
            'auditable_type' => User::class,
            'auditable_id' => $user->id,
        ]);

        $log = AuditLog::where('event', 'security.password_changed')->first();

        $this->assertNotNull($log);
        $this->assertNotNull($log->old_values['password_last_changed']);
        $this->assertNotNull($log->new_values['password_last_changed']);
        $this->assertNotNull($log->metadata['via']);
        $this->assertArrayHasKey('ip_address', $log->metadata);
        $this->assertArrayHasKey('user_agent', $log->metadata);
    }
}
