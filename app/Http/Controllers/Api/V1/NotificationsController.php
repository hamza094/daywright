<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Actions\BuildPaginatedPayloadAction;
use App\Http\Controllers\Api\ApiController;
use App\Http\Requests\Api\V1\NotificationStatusUpdateRequest;
use App\Http\Resources\Api\V1\NotificationResource;
use App\Services\Api\V1\NotificationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class NotificationsController extends ApiController
{
    /**
     * Display a listing of the user's notifications.
     */
    public function index(
        Request $request,
        BuildPaginatedPayloadAction $buildPaginatedPayloadAction,
        NotificationService $notificationService,
    ): JsonResponse {
        $paginator = $notificationService->paginateForUser(
            $this->authenticatedUser(),
            $request->query('filter'),
        );

        if ($paginator->isEmpty()) {
            return response()->json(['message' => 'No notifications found'], Response::HTTP_OK);
        }

        $payload = $buildPaginatedPayloadAction->handle($paginator, NotificationResource::class);

        return response()->json($payload, Response::HTTP_OK);
    }

    /**
     * Mark all notifications as read.
     */
    public function markAllAsRead(NotificationService $notificationService): JsonResponse
    {
        $notificationService->markAllAsRead($this->authenticatedUser());

        return response()->json([
            'message' => 'All users notifications marked as read.',
        ], Response::HTTP_OK);
    }

    /**
     * Remove the specified notification.
     */
    public function destroy(string $notification, NotificationService $notificationService): JsonResponse
    {
        $notificationService->deleteForUser($this->authenticatedUser(), $notification);

        return response()->json([
            'message' => 'Notification deleted successfully.',
        ], Response::HTTP_OK);
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

        return response()->json(['message' => 'Notification status updated.'], Response::HTTP_OK);
    }
}
