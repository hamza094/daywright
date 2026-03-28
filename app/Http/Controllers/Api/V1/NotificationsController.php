<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Enums\NotificationFilter;
use App\Http\Controllers\Api\ApiController;
use App\Http\Resources\Api\V1\NotificationResource;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;

class NotificationsController extends ApiController
{
    /**
     * Display a listing of the user's notifications.
     */
    public function index(Request $request): LengthAwarePaginator
    {
        $notifications = $this->authenticatedUser()
            ->notifications()
            ->latest()
            ->when($request->filter === NotificationFilter::READ->value, fn ($query) => $query->whereNotNull('read_at'))
            ->when($request->filter === NotificationFilter::UNREAD->value, fn ($query) => $query->whereNull('read_at'))
            ->get();

        return NotificationResource::collection($notifications)->paginate(25);
    }

    /**
     * Mark all notifications as read.
     */
    public function markAllAsRead(): JsonResponse
    {
        $this->authenticatedUser()->unreadNotifications()->update([
            'read_at' => now(),
        ]);

        return response()->json([
            'message' => 'All users notifications marked as read.',
        ], 200);
    }

    /**
     * Remove the specified notification.
     */
    public function destroy($notification): JsonResponse
    {
        $this->authenticatedUser()->notifications()
            ->findOrFail($notification)->delete();

        return response()->json([
            'message' => 'Notification deleted successfully.',
        ], 200);
    }

    /**
     * Update the status of a notification.
     */
    public function updateStatus(Request $request, $notification): JsonResponse
    {
        $request->validate(['status' => 'required|in:read,unread']);

        $userNotification = $this->authenticatedUser()->notifications()->findOrFail($notification);

        $request->status === 'read'
            ? $userNotification->markAsRead()
            : $userNotification->update(['read_at' => null]);

        return response()->json(['message' => 'Notification status updated.']);
    }
}
