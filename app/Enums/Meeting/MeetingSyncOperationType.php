<?php

declare(strict_types=1);

namespace App\Enums\Meeting;

enum MeetingSyncOperationType: string
{
    case Create = 'create';
    case Update = 'update';
    case Delete = 'delete';
}
