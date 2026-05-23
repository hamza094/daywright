<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\NotificationFilter;
use App\Models\User;
use Illuminate\Notifications\DatabaseNotification;
use Illuminate\Pagination\LengthAwarePaginator;

class UserNotificationService
{
    /**
     * @return LengthAwarePaginator<int, DatabaseNotification>
     */
    public function paginateForUser(User $user, ?string $status, int $perPage = 25): LengthAwarePaginator
    {
        /** @var \Illuminate\Database\Eloquent\Builder<DatabaseNotification> $query */
        $query = $user->notifications()->latest();

        $this->applyStatusFilter($query, $status);

        return $query->paginate($perPage);
    }

    public function markAllAsRead(User $user): void
    {
        $user->unreadNotifications()->update([
            'read_at' => now(),
        ]);
    }

    public function deleteForUser(User $user, string $notificationId): void
    {
        $this->findUserNotification($user, $notificationId)->delete();
    }

    public function updateStatus(User $user, string $notificationId, string $status): void
    {
        $notification = $this->findUserNotification($user, $notificationId);

        $status === NotificationFilter::READ->value
            ? $notification->markAsRead()
            : $notification->update(['read_at' => null]);
    }

    private function findUserNotification(User $user, string $notificationId): DatabaseNotification
    {
        /** @var DatabaseNotification $notification */
        $notification = $user->notifications()->findOrFail($notificationId);

        return $notification;
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
