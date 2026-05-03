<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Api\ApiController;
use App\Http\Requests\Api\V1\NotificationStatusUpdateRequest;
use App\Http\Resources\Api\V1\NotificationResource;
use App\Services\Api\V1\NotificationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class NotificationsController extends ApiController
{
    /**
     * Display a listing of the user's notifications.
     */
    public function index(
        Request $request,
        NotificationService $notificationService,
    ): JsonResponse {
        $paginator = $notificationService->paginateForUser(
            $this->authenticatedUser(),
            $request->query('filter'),
        );

        return NotificationResource::collection($paginator)->response();
    }

    /**
     * Mark all notifications as read.
     */
    public function markAllAsRead(NotificationService $notificationService): JsonResponse
    {
        $notificationService->markAllAsRead($this->authenticatedUser());

        return $this->respondWithMessage('All users notifications marked as read.');
    }

    /**
     * Remove the specified notification.
     */
    public function destroy(string $notification, NotificationService $notificationService): JsonResponse
    {
        $notificationService->deleteForUser($this->authenticatedUser(), $notification);

        return $this->respondWithMessage('Notification deleted successfully.');
    }

    /**
     * Update the status of a notification.
     */
    public function updateStatus(
        NotificationStatusUpdateRequest $request,
        string $notification,
        NotificationService $notificationService,
    ): JsonResponse {
        $status = $request->validated('status');

        $notificationService->updateStatus($this->authenticatedUser(), $notification, $status);

        return $this->respondWithMessage('Notification status updated.');
    }
}
