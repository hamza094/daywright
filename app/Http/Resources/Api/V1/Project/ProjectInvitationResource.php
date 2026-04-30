<?php

declare(strict_types=1);

namespace App\Http\Resources\Api\V1\Project;

use App\Http\Resources\Api\V1\User\UserSummaryResource;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use JsonSerializable;
use Override;

/**
 * @mixin \App\Models\Project
 */
class ProjectInvitationResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array|\Illuminate\Contracts\Support\Arrayable|JsonSerializable
     */
    #[Override]
    public function toArray(Request $request): array
    {
        return [
            /**
             * Project ID
             *
             * @example 1
             */
            'id' => $this->id,

            /**
             * Project name
             *
             * @example "My Project"
             */
            'name' => $this->name,

            /**
             * Invitation status (e.g. pending, accepted, rejected)
             *
             * @example "pending"
             */
            'status' => $this->status,

            /**
             * Project slug
             *
             * @example "my-project"
             */
            'slug' => $this->slug,

            /**
             * Date/time when the invitation was sent (formatted)
             *
             * @example "2025-07-09 14:00:00"
             */
            'invitation_sent_at' => $this->pivot->created_at->format(config('app.date_formats.exact')),

            /**
             * Project owner details
             *
             * @example {"uuid":176890,"name":"Owner Name",...}
             */
            'owner' => new UserSummaryResource($this->whenLoaded('user')),

            /**
             * Project creation date (human readable)
             *
             * @example "2 days ago"
             */
            'created_at' => $this->created_at->diffForHumans(),

            /**
             * Links related to the project invitation.
             *
             * @example {
             *   "project": "/api/v1/projects/my-project"
             * }
             */
            'links' => [
                'project' => $this->path(),
            ],
        ];
    }
}
