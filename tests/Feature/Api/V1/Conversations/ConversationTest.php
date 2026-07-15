<?php

declare(strict_types=1);

namespace Tests\Feature\Api\V1\Conversations;

use App\Events\NewMessage;
use App\Models\Conversation;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;
use Tests\Traits\InteractsWithPaddle;
use Tests\Traits\ProjectSetup;

class ConversationTest extends TestCase
{
    use InteractsWithPaddle, ProjectSetup, RefreshDatabase;

    /** @test */
    public function allowed_user_can_see_project_conversations(): void
    {
        $conversation = Conversation::factory()->create(['project_id' => $this->project->id,
        ]);

        $response = $this->withoutExceptionHandling()->getJson($this->apiV1ProjectRoute('conversations.index', $this->project));

        $response->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonStructure([
                'data',
                'links' => ['next', 'prev'],
                'meta' => ['per_page', 'next_cursor', 'prev_cursor'],
            ])
            ->assertJsonFragment([
                'message' => $conversation->message,
            ]);

        $this->assertEquals($this->apiV1ProjectRoute('projects.show', $this->project), $response->json('data.0.links.project'));
    }

    /** @test */
    public function conversation_index_can_limit_results_per_page(): void
    {
        Conversation::factory()->count(4)->create([
            'project_id' => $this->project->id,
        ]);

        $response = $this->getJson($this->apiV1ProjectRoute('conversations.index', $this->project, query: [
            'per_page' => 2,
        ]));

        $response->assertOk()
            ->assertJsonCount(2, 'data')
            ->assertJsonPath('meta.per_page', 2)
            ->assertNotNull($response->json('meta.next_cursor'));
    }

    /** @test */
    public function conversation_index_returns_empty_paginated_payload(): void
    {
        $this->getJson($this->apiV1ProjectRoute('conversations.index', $this->project))
            ->assertOk()
            ->assertJsonCount(0, 'data')
            ->assertJsonStructure([
                'data',
                'links' => ['next', 'prev'],
                'meta' => ['per_page', 'next_cursor', 'prev_cursor'],
            ]);
    }

    /** @test */
    public function conversation_index_rejects_unsupported_top_level_query_parameters(): void
    {
        $this->getJson($this->apiV1ProjectRoute('conversations.index', $this->project, query: [
            'sort' => 'created_at',
            'include' => 'user',
            'random' => 'value',
        ]))
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['sort', 'include', 'random']);
    }

    /** @test */
    public function conversation_index_supports_cursor_pagination(): void
    {
        Conversation::factory()->count(4)->create([
            'project_id' => $this->project->id,
        ]);

        // Fetch first page with per_page=2
        $firstPage = $this->getJson($this->apiV1ProjectRoute('conversations.index', $this->project, query: [
            'per_page' => 2,
        ]));

        $firstPage->assertOk()
            ->assertJsonCount(2, 'data')
            ->assertNotNull($firstPage->json('meta.next_cursor'));

        $nextCursor = $firstPage->json('meta.next_cursor');

        // Fetch next page using the returned next_cursor
        $secondPage = $this->getJson($this->apiV1ProjectRoute('conversations.index', $this->project, query: [
            'per_page' => 2,
            'cursor' => $nextCursor,
        ]));

        $secondPage->assertOk()
            ->assertJsonCount(2, 'data');
    }

    /** @test */
    public function allowed_user_participates_in_project_chat(): void
    {
        Event::fake();

        $message = 'random chat conversation';

        $this->postJson($this->apiV1ProjectRoute('conversations.store', $this->project), ['message' => $message,
            'user_id' => $this->user->id], ['Idempotency-Key' => 'test-key-existing'])
            ->assertCreated()
            ->assertJsonPath('data.message', $message);

        $this->assertDatabaseHas('conversations', [
            'message' => $message]);

        Event::assertDispatched(NewMessage::class);
    }

    /** @test */
    public function chat_validation_check(): void
    {
        $response = $this->postJson($this->apiV1ProjectRoute('conversations.store', $this->project), ['message' => null,
            'user_id' => $this->user->id], ['Idempotency-Key' => 'test-key-validation']);

        $response->assertJsonValidationErrors('message');
    }

    /** @test */
    public function allowed_user_store_file_in_chat(): void
    {
        Storage::fake('s3');

        $file = UploadedFile::fake()->image('file.jpg')->size(700);

        $this->postJson($this->apiV1ProjectRoute('conversations.store', $this->project), [
            'message' => 'abra ka dabra',
            'file' => $file,
            'user_id' => $this->user->id,
        ], ['Idempotency-Key' => 'test-key-file']);

        $uploadedFile = 'conversations/'.$this->project->id.'_'.$file->hashName();

        Storage::disk('s3')->assertExists($uploadedFile);
    }

    /** @test */
    public function allowed_user_can_delete_conversation(): void
    {
        Storage::fake('s3');

        Storage::disk('s3')->put('photo1.jpg', 'test');

        $conversation = Conversation::factory()->create([
            'project_id' => $this->project->id,
            'user_id' => $this->user->id,
            'file' => 'photo1.jpg',
        ]);

        $this->deleteJson($this->apiV1ProjectRoute('conversations.destroy', $this->project, [
            'conversation' => $conversation,
        ]))
            ->assertOk()
            ->assertJsonPath('message', 'Conversation deleted successfully.');

        $this->assertModelMissing($conversation);

        Storage::disk('s3')->assertMissing('photo1.jpg');
    }

    /** @test */
    public function free_user_cannot_create_conversation(): void
    {
        // Ensure user is on Free plan
        $this->user->subscriptions()->delete();
        $this->user->customer()->delete();

        // Re-enable subscription middleware for this test
        $this->withMiddleware([\App\Http\Middleware\CheckSubscription::class]);

        $message = 'random chat conversation';

        $this->postJson($this->apiV1ProjectRoute('conversations.store', $this->project), [
            'message' => $message,
            'user_id' => $this->user->id,
        ], ['Idempotency-Key' => 'test-key-1'])
            ->assertForbidden()
            ->assertJsonPath('message', 'Access denied. An active subscription is required to perform this action.');
    }

    /** @test */
    public function free_user_cannot_create_conversation_with_attachment(): void
    {
        // Ensure user is on Free plan
        $this->user->subscriptions()->delete();
        $this->user->customer()->delete();

        // Re-enable subscription middleware for this test
        $this->withMiddleware([\App\Http\Middleware\CheckSubscription::class]);

        Storage::fake('s3');

        $file = UploadedFile::fake()->image('file.jpg')->size(700);

        $this->postJson($this->apiV1ProjectRoute('conversations.store', $this->project), [
            'message' => 'abra ka dabra',
            'file' => $file,
            'user_id' => $this->user->id,
        ], ['Idempotency-Key' => 'test-key-2'])
            ->assertForbidden()
            ->assertJsonPath('message', 'Access denied. An active subscription is required to perform this action.');
    }

    /** @test */
    public function free_user_can_list_conversations(): void
    {
        // Ensure user is on Free plan
        $this->user->subscriptions()->delete();
        $this->user->customer()->delete();

        $conversation = Conversation::factory()->create([
            'project_id' => $this->project->id,
        ]);

        $this->getJson($this->apiV1ProjectRoute('conversations.index', $this->project))
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonFragment([
                'message' => $conversation->message,
            ]);
    }

    /** @test */
    public function trial_user_can_create_conversation(): void
    {
        $fake = $this->fakeSubscription();
        $fake->setState($this->user, 'trialing');

        $message = 'trial chat conversation';

        $this->postJson($this->apiV1ProjectRoute('conversations.store', $this->project), [
            'message' => $message,
            'user_id' => $this->user->id,
        ], ['Idempotency-Key' => 'test-key-3'])
            ->assertCreated()
            ->assertJsonPath('data.message', $message);
    }

    /** @test */
    public function subscribed_user_can_create_conversation(): void
    {
        $fake = $this->fakeSubscription();
        $fake->setState($this->user, 'active');

        $message = 'subscribed chat conversation';

        $this->postJson($this->apiV1ProjectRoute('conversations.store', $this->project), [
            'message' => $message,
            'user_id' => $this->user->id,
        ], ['Idempotency-Key' => 'test-key-4'])
            ->assertCreated()
            ->assertJsonPath('data.message', $message);
    }
}
