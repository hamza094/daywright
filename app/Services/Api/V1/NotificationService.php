<?php

declare(strict_types=1);

namespace App\Services\Api\V1;

use App\Enums\NotificationFilter;
use App\Models\User;
use Illuminate\Notifications\DatabaseNotification;
use Illuminate\Pagination\LengthAwarePaginator;

class NotificationService
{
    public function paginateForUser(User $user, ?string $filter, int $perPage = 25): LengthAwarePaginator
    {
        return $user->notifications()
            ->latest()
            ->when($filter === NotificationFilter::READ->value, fn ($query) => $query->whereNotNull('read_at'))
            ->when($filter === NotificationFilter::UNREAD->value, fn ($query) => $query->whereNull('read_at'))
            ->paginate($perPage);
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
}
