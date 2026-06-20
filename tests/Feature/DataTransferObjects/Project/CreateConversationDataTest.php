<?php

declare(strict_types=1);

namespace Tests\Feature\DataTransferObjects\Project;

use App\DataTransferObjects\Project\CreateConversationData;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

final class CreateConversationDataTest extends TestCase
{
    #[Test]
    public function it_builds_conversation_data_from_a_payload_and_stored_file(): void
    {
        $data = CreateConversationData::fromArray([
            'message' => 'Can someone review this?',
        ])->withStoredFile('conversations/10_attachment.pdf');

        $this->assertSame([
            'message' => 'Can someone review this?',
            'file' => 'conversations/10_attachment.pdf',
        ], $data->toArray());
    }
}
