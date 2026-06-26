<?php

declare(strict_types=1);

namespace Tests\Feature\DataTransferObjects\Project;

use App\DataTransferObjects\Project\ProjectMessageData;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

final class ProjectMessageDataTest extends TestCase
{
    #[Test]
    public function it_builds_project_message_data_from_a_payload(): void
    {
        $data = ProjectMessageData::fromArray([
            'message' => 'Launch update',
            'subject' => 'Launch notice',
            'mail' => 'true',
            'sms' => '0',
            'users' => [
                ['id' => 5],
                ['user_id' => 8],
                (object) ['id' => 13],
                '21',
                '',
                null,
            ],
            'delivered_at' => '2026-05-01T07:30:00+00:00',
        ]);

        $this->assertSame([
            'message' => 'Launch update',
            'subject' => 'Launch notice',
            'mail' => true,
            'sms' => false,
            'delivered_at' => '2026-05-01T07:30:00+00:00',
            'recipient_ids' => [5, 8, 13, 21],
        ], $data->toArray());
    }
}
