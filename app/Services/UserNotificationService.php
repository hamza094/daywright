<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\NotificationFilter;
use App\Models\User;
use App\Repository\NotificationRepository;
use Illuminate\Pagination\CursorPaginator;

final readonly class UserNotificationService
{
    public function __construct(
        private NotificationRepository $notificationRepository,
    ) {}

    /**
     * @return CursorPaginator<int, \Illuminate\Notifications\DatabaseNotification>
     */
    public function paginateForUser(User $user, ?string $status, int $perPage = 25): CursorPaginator
    {
        return $this->notificationRepository->paginateForUser(
            $user,
            $status,
            $perPage,
        );
    }

    public function markAllAsRead(User $user): void
    {
        $user->unreadNotifications()->update([
            'read_at' => now(),
        ]);
    }

    public function deleteForUser(User $user, string $notificationId): void
    {
        $notification = $this->notificationRepository->findUserNotification(
            $user,
            $notificationId,
        );

        $notification->delete();
    }

    public function updateStatus(User $user, string $notificationId, string $status): void
    {
        $notification = $this->notificationRepository->findUserNotification(
            $user,
            $notificationId,
        );

        $status === NotificationFilter::READ->value
            ? $notification->markAsRead()
            : $notification->update(['read_at' => null]);
    }
}
