<?php

declare(strict_types=1);

namespace App\Enums\Meeting;

enum MeetingTokenAction: string
{
    case Start = 'start';
    case Join = 'join';
}
