<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Api\ApiController;
use App\Http\Requests\Api\V1\NotificationIndexRequest;
use App\Http\Requests\Api\V1\NotificationStatusUpdateRequest;
use App\Http\Resources\Api\V1\NotificationResource;
use App\Services\NotificationService;
use Dedoc\Scramble\Attributes\Response as ScrambleResponse;
use Illuminate\Http\JsonResponse;

class NotificationsController extends ApiController
{
    /**
     * Display a paginated listing of the authenticated user's notifications.
     *
     * Returns the notification feed using Laravel-style pagination links and metadata.
     */
    #[ScrambleResponse(
        status: 200,
        description: 'Paginated notification feed with Laravel-style pagination metadata and links.',
        type: 'array{data: array<int, NotificationResource>, meta: array{current_page: int, from: int|null, last_page: int, links: array<int, array{url: string|null, label: string, active: bool}>, path: string, per_page: int, to: int|null, total: int}, links: array{first: string|null, last: string|null, prev: string|null, next: string|null}}',
    )]
    public function index(
        NotificationIndexRequest $request,
        NotificationService $notificationService,
    ): JsonResponse {
        $paginator = $notificationService->paginateForUser(
            $this->authenticatedUser(),
            $request->statusFilter(),
            $request->perPage(),
        );

        return NotificationResource::collection($paginator)->response();
    }

    /**
     * Mark all notifications as read.
     *
     * Marks every notification for the authenticated user as read in a single operation.
     */
    public function markAllAsRead(NotificationService $notificationService): JsonResponse
    {
        $notificationService->markAllAsRead($this->authenticatedUser());

        return $this->respondWithMessage('All users notifications marked as read.');
    }

    /**
     * Remove the specified notification.
     *
     * Deletes one notification belonging to the authenticated user.
     */
    public function destroy(string $notification, NotificationService $notificationService): JsonResponse
    {
        $notificationService->deleteForUser($this->authenticatedUser(), $notification);

        return $this->respondWithMessage('Notification deleted successfully.');
    }

    /**
     * Update the status of a notification.
     *
     * Changes the read state for a single notification belonging to the authenticated user.
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
