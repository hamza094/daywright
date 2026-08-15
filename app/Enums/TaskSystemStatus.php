<?php

declare(strict_types=1);

namespace App\Enums;

enum TaskSystemStatus: int
{
    case Pending = 1;
    case InProgress = 2;
    case UnderReview = 3;
    case Completed = 4;
    case Cancelled = 5;

    /**
     * All known status IDs
     *
     * @return array<int>
     */
    public static function all(): array
    {
        return [
            self::Pending->value,
            self::InProgress->value,
            self::UnderReview->value,
            self::Completed->value,
            self::Cancelled->value,
        ];
    }

    /**
     * Statuses considered active (not completed/cancelled)
     *
     * @return array<int>
     */
    public static function active(): array
    {
        return [
            self::Pending->value,
            self::InProgress->value,
            self::UnderReview->value,
        ];
    }
}
