<?php

declare(strict_types=1);

namespace App\Repository;

use App\Enums\NotificationFilter;
use App\Models\User;
use Illuminate\Notifications\DatabaseNotification;
use Illuminate\Pagination\CursorPaginator;

class NotificationRepository
{
    /**
     * @return CursorPaginator<int, DatabaseNotification>
     */
    public function paginateForUser(User $user, ?string $status, int $perPage = 25): CursorPaginator
    {
        /** @var \Illuminate\Database\Eloquent\Builder<DatabaseNotification> $query */
        $query = $user->notifications()->latest()->orderByDesc('id');

        $this->applyStatusFilter($query, $status);

        return $query->cursorPaginate($perPage);
    }

    public function findUserNotification(User $user, string $notificationId): DatabaseNotification
    {
        return $user->notifications()->findOrFail($notificationId);
    }

    private function applyStatusFilter(mixed $query, ?string $status): void
    {
        if ($status === NotificationFilter::READ->value) {
            $query->whereNotNull('read_at');

            return;
        }

        if ($status === NotificationFilter::UNREAD->value) {
            $query->whereNull('read_at');
        }
    }
}
