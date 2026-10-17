<?php

declare(strict_types=1);

namespace App\Enums\Subscription;

enum SubscriptionOperationStatus: string
{
    case Pending = 'pending';
    case Processing = 'processing';
    case Completed = 'completed';
    case Failed = 'failed';
    case Unknown = 'unknown';
    case ManualReview = 'manual_review';

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }

    public function isTerminal(): bool
    {
        return match ($this) {
            self::Completed, self::Failed => true,
            self::Pending, self::Processing, self::Unknown, self::ManualReview => false,
        };
    }

    public function isNonTerminal(): bool
    {
        return ! $this->isTerminal();
    }

    public function canTransitionTo(self $target): bool
    {
        return match ($this) {
            self::Pending => $target === self::Processing,
            self::Processing => in_array($target, [self::Completed, self::Failed, self::Unknown, self::ManualReview], true),
            self::Unknown => in_array($target, [self::Processing, self::ManualReview], true),
            self::ManualReview => in_array($target, [self::Completed, self::Failed], true),
            self::Completed, self::Failed => false,
        };
    }
}
