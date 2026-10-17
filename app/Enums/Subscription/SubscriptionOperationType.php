<?php

declare(strict_types=1);

namespace App\Enums\Subscription;

enum SubscriptionOperationType: string
{
    case Swap = 'swap';
    case Cancel = 'cancel';

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }

    public function label(): string
    {
        return match ($this) {
            self::Swap => 'Swap',
            self::Cancel => 'Cancel',
        };
    }
}
