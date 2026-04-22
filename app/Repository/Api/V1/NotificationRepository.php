<?php

declare(strict_types=1);

namespace App\Repository\Api\V1;

use App\Enums\NotificationFilter;
use App\Models\User;
use Illuminate\Pagination\CursorPaginator;

final class NotificationRepository
{
    private const int PER_PAGE = 25;

    /**
     * @return CursorPaginator<\Illuminate\Notifications\DatabaseNotification>
     */
    public function getUserNotifications(User $user, ?string $filter = null, ?string $cursor = null): CursorPaginator
    {
        return $user->notifications()
            ->select(['id', 'type', 'data', 'read_at', 'created_at'])
            ->when($filter === NotificationFilter::READ->value, fn ($query) => $query->whereNotNull('read_at'))
            ->when($filter === NotificationFilter::UNREAD->value, fn ($query) => $query->whereNull('read_at'))
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->cursorPaginate(self::PER_PAGE, ['*'], 'cursor', $cursor);
    }

    public function perPage(): int
    {
        return self::PER_PAGE;
    }
}
