<?php

declare(strict_types=1);

namespace App\Enums\Subscription;

enum SubscriptionOperationRecoveryOutcome: string
{
    case Recovered = 'recovered';
    case Unresolved = 'unresolved';
    case ManualReview = 'manual_review';
    case Skipped = 'skipped';
}
