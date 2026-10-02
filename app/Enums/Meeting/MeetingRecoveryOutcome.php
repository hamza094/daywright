<?php

declare(strict_types=1);

namespace App\Enums\Meeting;

enum MeetingRecoveryOutcome
{
    case Recovered;
    case Unresolved;
    case ManualReview;
    case Skipped;
}
