<?php

declare(strict_types=1);

namespace Tests\Feature\Api\V1;

use App\Actions\DeleteProfileAction;
use App\Mail\PasswordUpdate;
use App\Models\Project;
use App\Models\User;
use App\Models\UserInfo;
use App\Services\Api\V1\UserService;
use App\Traits\ProjectSetup;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class UserTest extends TestCase
{
    use ProjectSetup,RefreshDatabase;

    public static function dataProvider(): array
    {
        return [
            [
                'newName' => 'john doe',
                'newUsername' => 'jane_doe',
                'newEmail' => 'john_doe@example.com',
                'newCompany' => 'Acme Inc.',
                'newMobile' => 1234567890,
            ],
        ];
    }

    #[Test]
    public function auth_user_see_all_users(): void
    {
        $response = $this->getJson('/api/v1/users');

        $response->assertStatus(200)
            ->assertJsonFragment([
                'name' => $this->user->name,
                'email' => $this->user->email,
            ]);

    }

    #[Test]
    public function auth_user_can_get_his_data(): void
    {
        $defaultTimezone = config('app.timezone', 'UTC');

        $response = $this->getJson($this->user->path());

        $response->assertStatus(200)
            ->assertJsonFragment([
                'id' => $this->user->id,
                'name' => $this->user->name,
                'email' => $this->user->email,
                'timezone' => $defaultTimezone,
            ]);
    }

    #[Test]
    #[DataProvider('dataProvider')]
    public function owner_can_update_his_data(string $newName, string $newUsername, string $newEmail, string $newCompany, int $newMobile): void
    {
        UserInfo::factory()->for($this->user)->create();

        $response = $this->patchJson($this->user->path(), [
            'name' => $newName,
            'email' => $newEmail,
            'username' => $newUsername,
            'company' => $newCompany,
            'mobile' => $newMobile,
        ]);

        $response->assertStatus(200)
            ->assertJsonFragment([
                'name' => $newName,
                'email' => $newEmail,
                'username' => $newUsername,
            ]);

        $this->assertDatabaseHas('users', [
            'id' => $this->user->id,
            'name' => $newName,
            'email' => $newEmail,
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

        $response = $this->patchJson($this->user->path(), [
            'timezone' => 'America/Los_Angeles',
        ]);

        $response->assertStatus(200)
            ->assertJsonFragment([
                'timezone' => 'America/Los_Angeles',
            ]);

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
        $newPassword = 'new_password';

        $userService = new UserService;

        $userService->updatePassword($user, $newPassword);

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
        $this->deleteJson('api/v1/users/'.$this->user->uuid);

        $this->assertSoftDeleted($this->user);

        // If projects are soft deleted on user delete:
        $this->assertSoftDeleted($this->project);
    }

    #[Test]
    public function it_permanently_deletes_user_and_handles_projects_after_15_days(): void
    {
        // Create a user and soft delete them 16 days ago
        $user = User::factory()->create(['deleted_at' => now()->subDays(16)]);
        $admin = User::factory()->create();
        $admin->markAsAdmin();

        $projectNoMembers = Project::factory()->create(['user_id' => $user->id]);

        $projectWithMembers = Project::factory()->create(['user_id' => $user->id]);
        $projectWithMembers->members()->attach($admin->id);

        (new DeleteProfileAction)->execute();

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
}
