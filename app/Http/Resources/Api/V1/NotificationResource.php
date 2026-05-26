<?php

declare(strict_types=1);

namespace App\Http\Resources\Api\V1;

use App\DataTransferObjects\Notification\NotificationPayloadData;
use App\Http\Resources\Api\V1\User\InvitedUserResource;
use Illuminate\Http\Resources\Json\JsonResource;
use JsonSerializable;
use Override;

class NotificationResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return array|\Illuminate\Contracts\Support\Arrayable|JsonSerializable
     */
    #[Override]
    public function toArray($request)
    {
        $payload = NotificationPayloadData::fromArray(is_array($this->data) ? $this->data : []);

        return [
            /**
             * Notification identifier.
             *
             * @example 7dcb4d40-4109-4b19-8424-4d031242f591
             */
            'id' => $this->id,
            /**
             * Short notification type name derived from the notification class.
             *
             * @example ProjectInvitation
             */
            'type' => class_basename($this->type),
            /**
             * Human-readable notification message.
             *
             * @example You have been invited to join Website Refresh.
             */
            'message' => $payload->message,
            /**
             * Relative or absolute link the client can open from the notification.
             *
             * @example /api/v1/projects/website-refresh
             */
            'link' => $payload->link,
            /**
             * User summary for the actor that triggered the notification, when available.
             */
            'notifier' => $this->when(
                $payload->notifier !== null,
                fn () => new InvitedUserResource((object) $payload->notifier->toArray())
            ),
            /**
             * Read timestamp in UTC ISO 8601 format, or null when unread.
             *
             * @example 2025-08-15T10:30:00+00:00
             */
            'read_at' => $this->read_at?->toIso8601String(),
            /**
             * Creation timestamp in UTC ISO 8601 format.
             *
             * @example 2025-08-15T09:00:00+00:00
             */
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
