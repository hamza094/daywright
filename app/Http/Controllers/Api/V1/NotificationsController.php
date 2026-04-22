<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Api\ApiController;
use App\Http\Resources\Api\V1\CursorPaginatedResourceCollection;
use App\Http\Resources\Api\V1\NotificationResource;
use App\Repository\Api\V1\NotificationRepository;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class NotificationsController extends ApiController
{
    /**
     * Display a listing of the user's notifications.
     */
    public function index(Request $request, NotificationRepository $repository): JsonResponse
    {
        $paginator = $repository->getUserNotifications(
            $this->authenticatedUser(),
            $request->query('filter'),
            $request->query('cursor'),
        );

        if ($paginator->isEmpty() && ! $request->has('cursor')) {
            return response()->json([
                'message' => 'No notifications found',
                'data' => [],
                'meta' => [
                    'per_page' => $repository->perPage(),
                    'next_cursor' => null,
                    'prev_cursor' => null,
                    'has_more' => false,
                ],
            ]);
        }

        return (new CursorPaginatedResourceCollection($paginator, NotificationResource::class))->response();
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
        $data = $request->validate(['status' => 'required|in:read,unread']);

        $userNotification = $this->authenticatedUser()->notifications()->findOrFail($notification);

        $data['status'] === 'read'
            ? $userNotification->markAsRead()
            : $userNotification->update(['read_at' => null]);

        return response()->json(['message' => 'Notification status updated.']);
    }
}
