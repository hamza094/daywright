<?php

declare(strict_types=1);

namespace Tests\Feature\DataTransferObjects\Task;

use App\DataTransferObjects\Task\TaskUpdateData;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

final class TaskUpdateDataTest extends TestCase
{
    #[Test]
    public function it_tracks_task_update_fields_and_notification_reset(): void
    {
        $data = TaskUpdateData::fromArray([
            'title' => 'Review handoff',
            'due_at' => '2026-05-01T07:30:00+00:00',
            'notified' => '1 Day Before',
            'status_id' => 3,
            'ignored' => 'value',
        ]);

        $this->assertTrue($data->hasDueAt());
        $this->assertSame('2026-05-01T07:30:00+00:00', $data->dueAt());
        $this->assertTrue($data->hasNotified());
        $this->assertSame('1 Day Before', $data->notified());
        $this->assertTrue($data->hasStatusUpdate());
        $this->assertSame([
            'title' => 'Review handoff',
            'due_at' => '2026-05-01T07:30:00+00:00',
            'notified' => '1 Day Before',
            'status_id' => 3,
            'notify_sent' => false,
        ], $data->withNotificationReset()->toArray());
    }
}
