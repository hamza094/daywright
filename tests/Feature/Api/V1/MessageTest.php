<?php

declare(strict_types=1);

namespace Tests\Feature\Api\V1;

use App\Models\Message;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Override;
use Tests\TestCase;
use Tests\Traits\ProjectSetup;

use function Safe\json_encode;

class MessageTest extends TestCase
{
    use ProjectSetup, RefreshDatabase {
        ProjectSetup::setUp as projectSetUp;
    }

    #[Override]
    protected function setUp(): void
    {
        // Run the trait setup (creates user, project, Sanctum acting, etc.)
        $this->projectSetUp();

        // Mark the test user as admin for all tests in this class
        $this->user->forceFill(['is_admin' => true])->save();
    }

    /** @test */
    public function operation_on_send_message(): void
    {
        $this->postJson($this->project->path().'/message', [
            'message' => 'this is project message',
            'users' => json_encode([$this->user->id]),
            'subject' => 'this is message subject',
            'sms' => true,
            'mail' => true,
        ]);

        $this->assertDatabaseHas('messages', ['type' => 'mail']);

        $this->assertDatabaseHas('messages', ['type' => 'sms']);
    }

    /** @test */
    public function message_option_must_be_selected(): void
    {
        $response = $this->postJson($this->project->path().'/message', [
            'message' => 'this is project message',
            'users' => json_encode(['71b88a29', '42892']),
        ]);

        $response->assertJsonValidationErrors('option');
    }

    /** @test */
    public function check_schedule_command_working(): void
    {
        Message::factory()->for($this->project)
            ->count(3)
            ->create(['delivered_at' => Carbon::yesterday()]);

        $this->assertCount(3, Message::messageScheduled()->get());

        $this->artisan('schedule:message')->assertok();

        $this->assertCount(0, $this->project->scheduledMessages());
    }

    /** @test */
    public function get_project_scheduled_messages(): void
    {
        Message::factory()->for($this->project)->count(4)
            ->create(['delivered_at' => Carbon::now()->addDay()]);

        $this->getJson($this->project->path().
         '/messages/scheduled')->assertok();

        $this->assertEquals($this->project->scheduledMessages()
            ->count(), $this->project->messages->count());
    }

    /** @test */
    public function project_message_can_be_deleted(): void
    {
        $message = Message::factory()->for($this->project)
            ->create();

        $this->deleteJson($this->project->path().'/messages/'.
               $message->id.'/delete');

        $this->assertModelMissing($message);
    }
}
