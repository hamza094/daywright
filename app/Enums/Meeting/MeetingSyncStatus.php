<?php

declare(strict_types=1);

namespace App\Enums\Meeting;

enum MeetingSyncStatus: string
{
    case Pending = 'pending';
    case Active = 'active';
    case Failed = 'failed';
    case Updating = 'updating';
    case UpdateFailed = 'update_failed';
    case Deleting = 'deleting';
    case DeleteFailed = 'delete_failed';
    case Deleted = 'deleted';

    public function acceptsZoomRuntimeWebhook(): bool
    {
        return $this === self::Active;
    }
}
