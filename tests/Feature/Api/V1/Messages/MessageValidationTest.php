<?php

declare(strict_types=1);

namespace Tests\Feature\Api\V1\Messages;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Override;
use Tests\TestCase;
use Tests\Traits\ProjectSetup;

use function Safe\json_encode;

class MessageValidationTest extends TestCase
{
    use ProjectSetup, RefreshDatabase {
        ProjectSetup::setUp as projectSetUp;
    }

    #[Override]
    protected function setUp(): void
    {
        // Run the trait setup (creates user, project, Sanctum acting, etc.)
        $this->projectSetUp();

        // Ensure the user is admin for message-related validation tests
        $this->user->forceFill(['is_admin' => true])->save();
    }

    /** @test */
    public function validate_message_errors(): void
    {
        $users = json_encode(User::factory(2)->create());

        $this->withHeaders($this->idempotencyHeaders())->postJson($this->apiV1ProjectRoute('projects.messages.store', $this->project), ['message' => null, 'users' => $users])
            ->assertUnprocessable()
            ->assertJsonMissingValidationErrors('data.message');
    }

    /** @test */
    public function check_message_option_select(): void
    {
        $users = json_encode(User::factory(2)->create());

        $this->withHeaders($this->idempotencyHeaders())->postJson($this->apiV1ProjectRoute('projects.messages.store', $this->project),
            ['message' => 'this is my post', 'users' => $users, 'mail' => null, 'sms' => null])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('option');
    }

    /** @test */
    public function delivered_at_must_be_iso_8601_with_timezone_offset(): void
    {
        $users = json_encode(User::factory(2)->create());

        $this->withHeaders($this->idempotencyHeaders())->postJson($this->apiV1ProjectRoute('projects.messages.store', $this->project), [
            'message' => 'this is my post',
            'users' => $users,
            'sms' => true,
            'delivered_at' => '2024-12-04T15:00:00',
        ])->assertUnprocessable()
            ->assertJsonValidationErrors('delivered_at');
    }

    /** @test */
    public function legacy_schedule_fields_are_rejected(): void
    {
        $users = json_encode(User::factory(2)->create());

        $this->withHeaders($this->idempotencyHeaders())->postJson($this->apiV1ProjectRoute('projects.messages.store', $this->project), [
            'message' => 'this is my post',
            'users' => $users,
            'sms' => true,
            'date' => '2024-12-04',
            'time' => '15:00:00',
        ])->assertUnprocessable()
            ->assertJsonValidationErrors(['date', 'time']);
    }
}
