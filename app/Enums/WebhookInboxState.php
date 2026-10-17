<?php

declare(strict_types=1);

namespace App\Enums;

enum WebhookInboxState: string
{
    case Received = 'received';
    case Processing = 'processing';
    case Completed = 'completed';
    case Failed = 'failed';
}
