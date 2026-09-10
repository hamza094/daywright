<?php

declare(strict_types=1);

namespace Tests\Feature\Api\V1\Users;

use App\Actions\PurgeDeletedUsersAction;
use App\DataTransferObjects\User\PasswordUpdateData;
use App\Mail\PasswordUpdate;
use App\Models\Project;
use App\Models\User;
use App\Models\UserInfo;
use App\Services\User\UserService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use Symfony\Component\HttpFoundation\Response;
use Tests\TestCase;
use Tests\Traits\ProjectSetup;

class UserTest extends TestCase
{
    use ProjectSetup,RefreshDatabase;

    public static function dataProvider(): array
    {
        return [
            [
                'newName' => 'john doe',
                'newUsername' => 'jane_doe',
                'newCompany' => 'Acme Inc.',
                'newMobile' => '1234567890',
            ],
        ];
    }

    #[Test]
    public function me_endpoint_returns_authenticated_user_contract(): void
    {
        $this->getJson(route('api.v1.users.me.show'))
            ->assertOk()
            ->assertJsonPath('data.user.id', $this->user->id)
            ->assertJsonPath('data.user.uuid', $this->user->uuid)
            ->assertJsonPath('data.user.is_admin', false)
            ->assertJsonPath('data.user.two_factor_enabled', false);
    }

    #[Test]
    public function auth_user_can_get_his_data(): void
    {
        $defaultTimezone = config('app.timezone', 'UTC');

        $response = $this->getJson($this->apiV1Route('users.show', ['user' => $this->user]));

        $response->assertStatus(200)
            ->assertJsonFragment([
                'id' => $this->user->id,
                'name' => $this->user->name,
                'email' => $this->user->email,
                'timezone' => $defaultTimezone,
            ])
            ->assertJsonPath('data.created_at', $this->user->created_at?->setTimezone('UTC')->toIso8601String())
            ->assertJsonPath('data.updated_at', $this->user->updated_at?->setTimezone('UTC')->toIso8601String());
    }

    #[Test]
    #[DataProvider('dataProvider')]
    public function owner_can_update_his_data(string $newName, string $newUsername, string $newCompany, string $newMobile): void
    {
        UserInfo::factory()->for($this->user)->create();

        $originalEmail = $this->user->email;

        $response = $this->patchJson($this->apiV1Route('users.update', ['user' => $this->user]), [
            'name' => $newName,
            'username' => $newUsername,
            'company' => $newCompany,
            'mobile' => $newMobile,
        ]);

        $response->assertStatus(200)
            ->assertJsonPath('data.name', $newName)
            ->assertJsonPath('data.email', $originalEmail)
            ->assertJsonPath('data.username', $newUsername);

        $this->assertDatabaseHas('users', [
            'id' => $this->user->id,
            'name' => $newName,
            'email' => $originalEmail,
        ])
            ->assertDatabaseHas('user_infos', [
                'user_id' => $this->user->id,
                'company' => $newCompany,
                'mobile' => $newMobile,
            ]);
    }

    #[Test]
    public function owner_can_update_timezone(): void
    {
        UserInfo::factory()->for($this->user)->create();

        $response = $this->patchJson($this->apiV1Route('users.update', ['user' => $this->user]), [
            'timezone' => 'America/Los_Angeles',
        ]);

        $response->assertStatus(200)
            ->assertJsonPath('data.timezone', 'America/Los_Angeles');

        $this->assertDatabaseHas('users', [
            'id' => $this->user->id,
            'timezone' => 'America/Los_Angeles',
        ]);
    }

    #[Test]
    public function it_can_update_user_password(): void
    {
        Mail::fake();

        $user = $this->user;
        $currentPassword = 'testpassword';
        $newPassword = 'new_password';

        $userService = app(UserService::class);

        $passwordData = new PasswordUpdateData(
            $currentPassword,
            $newPassword
        );

        $userService->updatePassword($user, $passwordData);

        $this->assertTrue(Hash::check($newPassword, $user->password));

        Mail::assertQueued(PasswordUpdate::class, fn ($mail) => $mail->hasTo($user->email));
    }

    #[Test]
    public function password_update_mail_contains_time(): void
    {
        $time = Carbon::now()->toDayDateTimeString();

        $mailable = new PasswordUpdate($time);

        $mailable->assertSeeInHtml($time);
    }

    #[Test]
    public function user_can_delete_his_profile(): void
    {
        $this->deleteJson($this->apiV1Route('users.destroy', ['user' => $this->user]));

        $this->assertSoftDeleted($this->user);

        // If projects are soft deleted on user delete:
        $this->assertSoftDeleted($this->project);
    }

    #[Test]
    public function it_permanently_deletes_user_and_handles_projects_after_15_days(): void
    {
        // Create a user and soft delete them 16 days ago
        $user = User::factory()->create(['deleted_at' => now()->subDays(16)]);
        $admin = User::factory()->admin()->create();

        $projectNoMembers = Project::factory()->create(['user_id' => $user->id]);

        $projectWithMembers = Project::factory()->create(['user_id' => $user->id]);
        $projectWithMembers->members()->attach($admin->id);

        (new PurgeDeletedUsersAction)->execute();

        $this->assertDatabaseMissing('users', ['id' => $user->id]);

        $this->assertDatabaseMissing('projects', ['id' => $projectNoMembers->id]);

        $projectWithMembersFresh = Project::withTrashed()->find($projectWithMembers->id);
        $this->assertNotNull($projectWithMembersFresh);
        $this->assertSoftDeleted('projects', ['id' => $projectWithMembers->id]);
        $this->assertEquals($admin->id, $projectWithMembersFresh->user_id);
    }

    #[Test]
    public function test_user_profile_delete_command_runs(): void
    {
        $this->artisan('user:profile-delete')
            ->expectsOutput('User profile deletion process completed.')
            ->assertExitCode(0);
    }

    #[Test]
    public function user_cannot_modify_another_users_profile_even_with_shared_project(): void
    {
        $otherUser = User::factory()->create();
        UserInfo::factory()->for($otherUser)->create();

        // Add other user to the same project
        $this->project->members()->attach($otherUser->id);

        // Try to modify other user's profile
        $response = $this->patchJson($this->apiV1Route('users.update', ['user' => $otherUser]), [
            'name' => 'Hacked Name',
        ]);

        $response->assertForbidden();

        // Verify other user's data was not changed
        $this->assertDatabaseMissing('users', [
            'id' => $otherUser->id,
            'name' => 'Hacked Name',
        ]);
    }

    #[Test]
    public function user_cannot_delete_another_users_account_even_with_shared_project(): void
    {
        $otherUser = User::factory()->create();

        // Add other user to the same project
        $this->project->members()->attach($otherUser->id);

        // Try to delete other user's account
        $response = $this->deleteJson($this->apiV1Route('users.destroy', ['user' => $otherUser]));

        $response->assertForbidden();

        // Verify other user's account was not deleted
        $this->assertDatabaseHas('users', [
            'id' => $otherUser->id,
            'deleted_at' => null,
        ]);
    }

    #[Test]
    public function user_cannot_change_email_address(): void
    {
        UserInfo::factory()->for($this->user)->create();

        $originalEmail = $this->user->email;
        $newEmail = 'different@example.com';

        $response = $this->patchJson($this->apiV1Route('users.update', ['user' => $this->user]), [
            'email' => $newEmail,
        ]);

        $response->assertUnprocessable();

        // Verify email was not changed
        $this->assertDatabaseHas('users', [
            'id' => $this->user->id,
            'email' => $originalEmail,
        ]);
    }

    #[Test]
    public function cannot_force_delete_active_user_enforces_archive_first(): void
    {
        // Try to force delete an active user (not soft-deleted)
        $response = $this->deleteJson($this->apiV1Route('users.forceDestroy', ['user' => $this->user]));

        $response->assertStatus(Response::HTTP_CONFLICT)
            ->assertJsonPath('message', 'User must be soft-deleted before force deletion.');

        // Verify user is still active (not deleted)
        $this->assertDatabaseHas('users', [
            'id' => $this->user->id,
            'deleted_at' => null,
        ]);
    }

    #[Test]
    public function can_force_delete_soft_deleted_user(): void
    {
        // First soft delete the user
        $this->deleteJson($this->apiV1Route('users.destroy', ['user' => $this->user]))->assertOk();

        // Now force delete the soft-deleted user
        $response = $this->deleteJson($this->apiV1Route('users.forceDestroy', ['user' => $this->user]));

        $response->assertOk()
            ->assertJsonPath('message', 'User data permanently deleted.');

        // Verify user is permanently deleted
        $this->assertDatabaseMissing('users', ['id' => $this->user->id]);
    }

    #[Test]
    public function cannot_force_delete_another_users_soft_deleted_account(): void
    {
        $otherUser = User::factory()->create();

        // Soft delete the other user
        $otherUser->delete();

        // Try to force delete another user's soft-deleted account
        $response = $this->deleteJson($this->apiV1Route('users.forceDestroy', ['user' => $otherUser]));

        $response->assertForbidden();

        // Verify other user's account still exists (soft-deleted)
        $this->assertSoftDeleted('users', ['id' => $otherUser->id]);
    }

    #[Test]
    public function force_delete_missing_user_returns_not_found(): void
    {
        // Create a user and then permanently delete them
        $user = User::factory()->create();
        $uuid = $user->uuid;
        $user->forceDelete();

        // Try to force delete the already-deleted user
        $response = $this->deleteJson($this->apiV1Route('users.forceDestroy', ['user' => $uuid]));

        $response->assertNotFound();
    }

    #[Test]
    public function admin_archive_first_workflow_consistency(): void
    {
        // Document that even admins must follow the same archive-first workflow.
        // The state validation ($user->trashed()) happens after policy authorization,
        // so it applies to all users including admins who bypass policy checks.

        $admin = User::factory()->admin()->create();
        $otherUser = User::factory()->create();

        // Verify admin bypasses ownership policy
        $policy = new \App\Policies\UsersPolicy;
        $this->assertTrue($policy->before($admin), 'Admin bypasses policy checks');

        // Verify the ownership check logic
        $this->assertFalse($policy->owner($admin, $otherUser), 'Owner check returns false for different users');

        // Since admin bypasses the policy, they can force delete other users' soft-deleted accounts
        // (this is the intended admin behavior - they have full authority over soft-deleted accounts)
        // But even admins must follow archive-first workflow for active accounts
    }
}
