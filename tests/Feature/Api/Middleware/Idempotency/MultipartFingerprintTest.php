<?php

declare(strict_types=1);

namespace Tests\Feature\Api\Middleware\Idempotency;

use App\Enums\FeatureFlag;
use App\Events\NewMessage;
use App\Models\Conversation;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Storage;
use Laravel\Pennant\Feature;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;
use Tests\Traits\ProjectSetup;

final class MultipartFingerprintTest extends TestCase
{
    use ProjectSetup;
    use RefreshDatabase;

    #[Test]
    public function matching_multipart_fields_and_file_contents_replay_the_response(): void
    {
        $this->prepareMessagingFeature();

        $firstFile = UploadedFile::fake()->image('attachment.jpg', 10, 10);
        $retryFile = UploadedFile::fake()->createWithContent('attachment.jpg', $firstFile->getContent());
        $headers = $this->idempotencyHeaders('matching-multipart-upload');
        $route = $this->apiV1ProjectRoute('conversations.store', $this->project);

        $firstResponse = $this->withHeaders($headers)
            ->post($route, ['file' => $firstFile])
            ->assertCreated();

        $secondResponse = $this->withHeaders($headers)
            ->post($route, ['file' => $retryFile])
            ->assertCreated()
            ->assertHeader('Idempotency-Replayed', 'true');

        $this->assertSame($firstResponse->json(), $secondResponse->json());
        $this->assertDatabaseCount((new Conversation)->getTable(), 1);
    }

    #[Test]
    public function changed_file_contents_with_the_same_key_are_rejected(): void
    {
        $this->prepareMessagingFeature();

        $headers = $this->idempotencyHeaders('changed-multipart-upload');
        $route = $this->apiV1ProjectRoute('conversations.store', $this->project);

        $this->withHeaders($headers)
            ->post($route, [
                'file' => UploadedFile::fake()->image('attachment.jpg', 10, 10),
            ])
            ->assertCreated();

        $this->withHeaders($headers)
            ->post($route, [
                'file' => UploadedFile::fake()->image('attachment.jpg', 20, 20),
            ])
            ->assertUnprocessable()
            ->assertJsonPath('message', 'Idempotency key already used with different request parameters.');

        $this->assertDatabaseCount((new Conversation)->getTable(), 1);
    }

    private function prepareMessagingFeature(): void
    {
        Storage::fake('s3');
        Event::fake([NewMessage::class]);
        Feature::for($this->user)->activate(FeatureFlag::ProjectMessaging->pennantName());
    }
}
