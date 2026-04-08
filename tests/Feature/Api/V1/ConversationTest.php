<?php

declare(strict_types=1);

namespace Tests\Feature\Api\V1;

use App\Events\NewMessage;
use App\Models\Conversation;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;
use Tests\Traits\ProjectSetup;

class ConversationTest extends TestCase
{
    use ProjectSetup,RefreshDatabase;

    /** @test */
    public function allowed_user_can_see_project_conversations(): void
    {
        $conversation = Conversation::factory()->create(['project_id' => $this->project->id,
        ]);

        $response = $this->withoutExceptionHandling()->getJson($this->project->path().'/conversations');

        $response->assertJsonFragment([
            'message' => $conversation->message,
        ]);
    }

    /** @test */
    public function allowed_user_participates_in_project_chat(): void
    {
        Event::fake();

        $message = 'random chat conversation';

        $this->postJson($this->project->path().'/conversations', ['message' => $message,
            'user_id' => $this->user->id]);

        $this->assertDatabaseHas('conversations', [
            'message' => $message]);

        Event::assertDispatched(NewMessage::class);
    }

    /** @test */
    public function chat_validation_check(): void
    {
        $response = $this->postJson($this->project->path().'/conversations', ['message' => null,
            'user_id' => $this->user->id]);

        $response->assertJsonValidationErrors('message');
    }

    /** @test */
    public function allowed_user_store_file_in_chat(): void
    {
        Storage::fake('s3');

        $file = UploadedFile::fake()->image('file.jpg')->size(700);

        $this->postJson($this->project->path().'/conversations', [
            'message' => 'abra ka dabra',
            'file' => $file,
            'user_id' => $this->user->id]);

        $uploadedFile = 'conversations/'.$this->project->id.'_'.$file->hashName();

        Storage::disk('s3')->assertExists($uploadedFile);
    }

    /** @test */
    public function allowed_user_can_delete_conversation(): void
    {
        Storage::fake('s3');

        $conversation = Conversation::factory()->create([
            'project_id' => $this->project->id,
            'user_id' => $this->user->id,
            'file' => UploadedFile::fake()->image('photo1.jpg'),
        ]);

        $this->deleteJson($this->project->path().'/conversations/'.$conversation->id);

        $this->assertModelMissing($conversation);

        Storage::disk('s3')->assertMissing('photo1.jpg');
    }
}
