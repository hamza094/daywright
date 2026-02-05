<?php

declare(strict_types=1);

namespace Tests\Feature\Api\V1;

use App\Models\User;
use App\Traits\ProjectSetup;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

use function Safe\json_encode;

class MessageValidationTest extends TestCase
{
    use ProjectSetup, RefreshDatabase {
        ProjectSetup::setUp as projectSetUp;
    }

    protected function setUp(): void
    {
        // Run the trait setup (creates user, project, Sanctum acting, etc.)
        $this->projectSetUp();

        // Ensure the user is admin for message-related validation tests
        $this->user->markAsAdmin();
    }

    /** @test */
    public function validate_message_errors(): void
    {
        $users = json_encode(User::factory(2)->create());

        $this->postJson($this->project->path().'/message', ['message' => null, 'users' => $users])
            ->assertUnprocessable()
            ->assertJsonMissingValidationErrors('data.message');
    }

    /** @test */
    public function check_message_option_select(): void
    {
        $users = json_encode(User::factory(2)->create());

        $this->postJson($this->project->path().'/message',
            ['message' => 'this is my post', 'users' => $users, 'mail' => null, 'sms' => null])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('option');
    }
}
