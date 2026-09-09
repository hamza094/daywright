<?php

declare(strict_types=1);

namespace App\Http\Resources\Api\V1\Project;

use App\Http\Resources\Api\V1\ActivityResource;
use App\Http\Resources\Api\V1\ApiResourceLink;
use App\Http\Resources\Api\V1\StageResource;
use App\Http\Resources\Api\V1\User\UserSummaryResource;
use Dedoc\Scramble\Attributes\SchemaName;
use Illuminate\Http\Resources\Json\JsonResource;
use Override;

/**
 * @mixin \App\Models\Project
 */
#[SchemaName('PublicProject')]
class ProjectResource extends JsonResource
{
    /**
     * @param  array<int, array{key: string, label: string, scope: string, limit: array{used: int|null, max: int|null}}>|null  $limits
     */
    public function __construct($resource, private readonly ?array $limits = null)
    {
        parent::__construct($resource);
    }

    /**
     * Transform the resource into an array.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return array<string, mixed>
     */
    #[Override]
    public function toArray($request)
    {
        $requestUser = $request->user();

        return [
            /**
             *  @example 1
             */
            'id' => $this->id,

            /**
             * @example the-dimension
             */
            'slug' => $this->slug,

            /**
             * @example The Dimension
             */
            'name' => $this->name,

            /**
             *  @example it describes what the project is about.
             */
            'about' => $this->about,

            /**
             *  @example This is project-specific note
             */
            'notes' => $this->notes,

            /**
             * Project creation timestamp in UTC ISO 8601 format.
             *
             * @format date-time
             *
             * @example 2024-06-04T00:00:00+00:00
             */
            'created_at' => $this->created_at?->toIso8601String(),

            /**
             * Project update timestamp in UTC ISO 8601 format.
             *
             * @format date-time
             *
             * @example 2024-06-04T12:30:00+00:00
             */
            'updated_at' => $this->updated_at?->toIso8601String(),

            /**
             * Project deletion timestamp in UTC ISO 8601 format when the project is trashed.
             *
             * @format date-time
             *
             * @example 2024-06-10T09:15:00+00:00
             */
            'deleted_at' => $this->when(
                ! empty($this->deleted_at),
                fn () => $this->deleted_at->toIso8601String()
            ),

            /** @var bool */
            'is_trashed' => $this->trashed(),

            /**
             * Last stage update timestamp in UTC ISO 8601 format.
             *
             * @format date-time
             *
             * @example 2024-06-10T09:15:00+00:00
             */
            'stage_updated_at' => $this->when(
                ! empty($this->stage_updated_at),
                fn () => $this->stage_updated_at->toIso8601String()
            ),

            /** @var bool */
            'ownerNotAuthorized' => $this->whenLoaded(
                'user',
                fn (): bool => $requestUser && $requestUser->is($this->user) && ! $requestUser->oauthConnections()->where('provider', 'zoom')->exists(),
            ),

            /** @var int */
            'days_limit' => config('app.project.abandonedLimit'),

            /**
             * Reason for postponing the project.
             *
             * @example Waiting for client approval
             */
            'postponed_reason' => $this->postponed_reason,

            /**
             * Basic details of the project owner.
             *
             * @example [data]
             */
            'user' => $this->whenLoaded(
                'user',
                fn (): UserSummaryResource => new UserSummaryResource($this->user),
            ),

            /**
             * Project health status based on activity and engagement.
             * Allowed values: hot (active), warm (moderate), cold (inactive).
             *
             * @example cold
             */
            'health_status' => $this->health_status,

            /**
             * Health score from 0-100 indicating project health.
             * Higher scores indicate healthier projects.
             *
             * @example 72.5
             */
            'health_score' => $this->health_score,

            /**
             * Health score calculation timestamp in UTC ISO 8601 format.
             *
             * @format date-time
             *
             * @example 2024-10-01T15:30:00+00:00
             */
            'health_score_calculated_at' => $this->health_score_calculated_at?->toIso8601String(),

            /**
             * Current stage information for the project.
             */
            'stage' => $this->whenLoaded(
                'stage',
                fn (): StageResource => new StageResource($this->stage),
            ),

            /**
             * List of active project members.
             */
            'members' => $this->whenLoaded(
                'activeMembers',
                fn () => UserSummaryResource::collection($this->activeMembers),
            ),

            /**
             * Current project-scoped usage and maximums for tracked subscription limits.
             *
             * */
            'limits' => $this->when(

                $this->limits !== null,
                fn () => ProjectLimitResource::collection($this->limits),
            ),

            /**
             * Limited list of recent project activities.
             */
            'activities' => $this->whenLoaded('limitedActivities', fn () => ActivityResource::collection($this->limitedActivities)),

            /**
             * API resource links for navigation.
             */
            'links' => [
                'self' => ApiResourceLink::project($this->resource),
            ],
        ];
    }
}
