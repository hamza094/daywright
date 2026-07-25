<?php

declare(strict_types=1);

namespace Tests\Feature\Api\V1\Users;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Override;
use Tests\TestCase;

class UserAvatarTest extends TestCase
{
    use RefreshDatabase;

    private const string USER_AVATAR_ROUTE = 'api.v1.user.avatar';

    private const string USER_AVATAR_REMOVE_ROUTE = 'api.v1.user.avatar.remove';

    #[Override]
    protected function setUp(): void
    {
        parent::setUp();
        // create a user
        $user = User::factory()->create([
            'email' => 'johndoe@example.org',
            'password' => Hash::make('testpassword'),
            'name' => 'jon doe',
        ]);

        Sanctum::actingAs(
            $user,
        );
    }

    /** @test */
    public function a_valid_avatar_must_be_provided(): void
    {
        $user = User::first();

        $this->postJson(route('api.v1.user.avatar', ['user' => $user->uuid]), ['avatar' => 'not-an-image'])->assertUnprocessable();
    }

    /** @test */
    public function authorize_user_may_add_avatar_to_his_profile(): void
    {
        $user = User::first();

        Storage::fake('s3');

        $file = UploadedFile::fake()->image('avatar.jpg');

        $this->withoutExceptionHandling()->postJson(route(self::USER_AVATAR_ROUTE, ['user' => $user->uuid]), [
            'avatar' => $file,
        ])->assertSuccessful()
            ->assertJsonPath('data.path', route('api.v1.users.show', ['user' => $user], false));

        $uploadedFile = 'avatars/'.$user->uuid.'_'.$file->hashName();

        Storage::disk('s3')->assertExists($uploadedFile);
    }

    /** @test */
    public function profile_owner_can_delete_his_avatar(): void
    {
        Storage::fake('s3');

        $user = User::first();

        $file = UploadedFile::fake()->image('avatar.jpg');

        $user->update([
            'avatar_path' => $file,
        ]);

        $response = $this->deleteJson(route(self::USER_AVATAR_REMOVE_ROUTE, ['user' => $user->uuid]));

        $response
            ->assertJson([
                'message' => 'User avatar removed successfully.',
            ])->assertStatus(200);

        $this->assertNull($user->fresh()->avatar_path);

        Storage::disk('s3')->assertMissing($file);

        $response = $this->deleteJson(route(self::USER_AVATAR_REMOVE_ROUTE, ['user' => $user->uuid]));

        $response
            ->assertJson([
                'message' => 'Resource not found.',
            ])->assertStatus(404);
    }

    /** @test */
    public function free_user_can_upload_avatar(): void
    {
        $user = User::first();

        // Ensure user is on Free plan
        $user->subscriptions()->delete();
        $user->customer()->delete();

        Storage::fake('s3');

        $file = UploadedFile::fake()->image('avatar.jpg');

        $this->postJson(route(self::USER_AVATAR_ROUTE, ['user' => $user->uuid]), [
            'avatar' => $file,
        ])->assertSuccessful()
            ->assertJsonPath('data.path', route('api.v1.users.show', ['user' => $user], false));

        $uploadedFile = 'avatars/'.$user->uuid.'_'.$file->hashName();

        Storage::disk('s3')->assertExists($uploadedFile);
    }
}
