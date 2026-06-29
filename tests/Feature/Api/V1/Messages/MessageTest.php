<?php

declare(strict_types=1);

namespace Tests\Feature\Api\V1\Messages;

use App\Models\Message;
use App\Services\Project\MessageService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Bus;
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
        $this->withHeaders($this->idempotencyHeaders())->postJson($this->apiV1ProjectRoute('projects.messages.store', $this->project), [
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
    public function message_can_be_scheduled_with_iso_delivered_at(): void
    {
        $deliveredAt = now()
            ->setTimezone('Asia/Karachi')
            ->addDay()
            ->setTime(12, 30, 0)
            ->toIso8601String();

        $this->withHeaders($this->idempotencyHeaders())->postJson($this->apiV1ProjectRoute('projects.messages.store', $this->project), [
            'message' => 'this is project message',
            'users' => json_encode([$this->user->id]),
            'sms' => true,
            'delivered_at' => $deliveredAt,
        ])->assertOk()
            ->assertJson([
                'message' => 'Messages Scheduled Successfully',
            ]);

        $message = Message::query()->sole();

        $this->assertSame(
            Carbon::parse($deliveredAt)->setTimezone('UTC')->toIso8601String(),
            $message->refresh()->delivered_at?->toIso8601String(),
        );
    }

    /** @test */
    public function message_option_must_be_selected(): void
    {
        $response = $this->withHeaders($this->idempotencyHeaders())->postJson($this->apiV1ProjectRoute('projects.messages.store', $this->project), [
            'message' => 'this is project message',
            'users' => json_encode(['71b88a29', '42892']),
        ]);

        $response->assertJsonValidationErrors('option');
    }

    /** @test */
    public function check_schedule_command_working(): void
    {
        Bus::fake();

        $messages = Message::factory()->for($this->project)
            ->count(3)
            ->create(['delivered_at' => Carbon::yesterday()]);

        $messages->each(function (Message $message): void {
            $message->users()->attach($this->user->id);
        });

        $this->assertCount(3, Message::messageScheduled()->get());

        $this->artisan('schedule:message')->assertok();

        Bus::assertBatchCount(3);
        $this->assertCount(0, $this->project->scheduledMessages());
    }

    /** @test */
    public function scheduled_message_dispatch_is_claimed_once_when_repeated(): void
    {
        Bus::fake();

        $message = Message::factory()
            ->for($this->project)
            ->create([
                'type' => 'mail',
                'delivered_at' => Carbon::yesterday(),
            ]);

        $message->users()->attach($this->user->id);

        $service = app(MessageService::class);

        $service->sendNow($this->project, $message);
        $service->sendNow($this->project, $message->fresh());

        Bus::assertBatchCount(1);
        $this->assertNotNull($message->fresh()->batch_id);
    }

    /** @test */
    public function message_scheduled_later_today_is_not_dispatched(): void
    {
        Bus::fake();

        $message = Message::factory()
            ->for($this->project)
            ->create([
                'type' => 'mail',
                'delivered_at' => Carbon::now()->addHours(5),
            ]);

        $message->users()->attach($this->user->id);

        $this->assertCount(0, Message::messageScheduled()->get());

        $this->artisan('schedule:message')->assertok();

        Bus::assertBatchCount(0);
        $this->assertNull($message->fresh()->batch_id);
    }

    /** @test */
    public function message_scheduled_at_or_before_now_dispatches_normally(): void
    {
        Bus::fake();

        $message = Message::factory()
            ->for($this->project)
            ->create([
                'type' => 'mail',
                'delivered_at' => Carbon::now()->subMinutes(5),
            ]);

        $message->users()->attach($this->user->id);

        $this->assertCount(1, Message::messageScheduled()->get());

        $this->artisan('schedule:message')->assertok();

        Bus::assertBatchCount(1);
        $this->assertNotNull($message->fresh()->batch_id);
    }

    /** @test */
    public function message_scheduled_later_today_appears_in_scheduled_list(): void
    {
        Message::factory()->for($this->project)->create([
            'delivered_at' => Carbon::now()->addHours(3),
        ]);

        $this->assertCount(1, $this->project->scheduledMessages());
    }

    /** @test */
    public function timezone_boundary_message_scheduled_in_user_timezone_stored_in_utc(): void
    {
        $userTimezone = 'Asia/Karachi';
        $deliveredAt = Carbon::now($userTimezone)
            ->addDay()
            ->setTime(14, 30, 0)
            ->toIso8601String();

        $this->withHeaders($this->idempotencyHeaders())->postJson($this->apiV1ProjectRoute('projects.messages.store', $this->project), [
            'message' => 'this is project message',
            'users' => json_encode([$this->user->id]),
            'sms' => true,
            'delivered_at' => $deliveredAt,
        ])->assertOk();

        $message = Message::query()->sole();

        // Verify stored in UTC
        $this->assertSame(
            Carbon::parse($deliveredAt)->setTimezone('UTC')->toIso8601String(),
            $message->refresh()->delivered_at?->toIso8601String(),
        );

        // Verify not dispatched yet (still in future in UTC)
        $this->assertCount(0, Message::messageScheduled()->get());
    }

    /** @test */
    public function get_project_scheduled_messages(): void
    {
        Message::factory()->for($this->project)->count(4)
            ->create(['delivered_at' => Carbon::now()->addDay()]);

        $this->getJson($this->apiV1ProjectRoute('projects.messages.scheduled', $this->project, query: [
            'per_page' => 2,
        ]))
            ->assertOk()
            ->assertJsonCount(2, 'data')
            ->assertJsonPath('meta.per_page', 2)
            ->assertJsonPath('meta.total', 4)
            ->assertJsonStructure([
                'data' => [
                    ['id', 'type', 'subject', 'message', 'delivered_at', 'created_at', 'users'],
                ],
                'links',
                'meta',
            ]);

        $this->assertEquals($this->project->scheduledMessages()
            ->count(), $this->project->messages->count());
    }

    /** @test */
    public function get_project_scheduled_messages_returns_empty_array_when_none_exist(): void
    {
        $this->getJson($this->apiV1ProjectRoute('projects.messages.scheduled', $this->project))
            ->assertOk()
            ->assertJsonCount(0, 'data')
            ->assertJsonStructure([
                'data',
                'links',
                'meta',
            ]);
    }

    /** @test */
    public function scheduled_messages_reject_unsupported_top_level_query_parameters(): void
    {
        $this->getJson($this->apiV1ProjectRoute('projects.messages.scheduled', $this->project, query: [
            'sort' => 'delivered_at',
            'include' => 'users',
            'random' => 'value',
        ]))
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['sort', 'include', 'random']);
    }

    /** @test */
    public function project_message_can_be_deleted(): void
    {
        $message = Message::factory()->for($this->project)
            ->create();

        $this->deleteJson($this->apiV1ProjectRoute('projects.messages.destroy', $this->project, [
            'message' => $message,
        ]))
            ->assertOk()
            ->assertJsonPath('message', 'Scheduled message deleted successfully.');

        $this->assertModelMissing($message);
    }
}
