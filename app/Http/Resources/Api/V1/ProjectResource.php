<?php

declare(strict_types=1);

namespace App\Http\Resources\Api\V1;

use Illuminate\Http\Resources\Json\JsonResource;
use JsonSerializable;
use Override;

/**
 * @mixin \App\Models\Project
 */
class ProjectResource extends JsonResource
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
        $showRoute = $request->routeIs('projects.show');

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
             * Date when the project was created, displayed in a human-readable format.
             *
             * @example 4 June 2024
             */
            'created_at' => $this->created_at->diffforHumans(),

            /**
             * Date when the project was last updated, displayed in a human-readable format.
             *
             * @example 4 June 2024
             */
            'updated_at' => $this->updated_at->diffforHumans(),

            /**
             * Shows the date the project was deleted if it is currently in the "trashed" state.
             *
             * @example 10 June 2024
             */
            'deleted_at' => $this->when(
                ! empty($this->deleted_at),
                fn () => $this->deleted_at->diffforHumans()
            ),

            'is_trashed' => $this->trashed(),

            /**
             * Date when the project's last stage was updated, formatted based on the application's date format configuration.
             *
             * @example 10 June 2024
             */
            'stage_updated_at' => $this->when(
                ! empty($this->stage_updated_at),
                fn () => $this->stage_updated_at
                    ->format(config('app.date_formats.exact'))
            ),

            'ownerNotAuthorized' => $this->when(
                $showRoute,
                auth()->user()->is($this->user) && ! auth()->user()->isConnectedToZoom(),
            ),

            'days_limit' => $this->when(
                $showRoute,
                config('app.project.abandonedLimit'),
            ),

            'postponed_reason' => $this->when(
                $showRoute,
                $this->postponed_reason,
            ),

            /**
             * Basic details of the project owner.
             *
             * @example [data]
             */
            'user' => $this->when($showRoute && $this->relationLoaded('user'), fn () => $this->user->only(['uuid', 'name', 'avatar_path', 'username', 'email'])),

            /**
             * Project status calculated on the based of score
             *
             * @example cold
             */
            'health_status' => $this->health_status,

            /**
             * @example 72.5
             */
            'health_score' => $this->health_score,

            /**
             * Timestamp when the health score was last calculated
             *
             * @example 2024-10-01 15:30:00
             */
            'health_score_calculated_at' => $this->health_score_calculated_at?->toDateTimeString(),

            /**
             * Current stage information for the project.
             */
            'stage' => $this->when($showRoute && $this->relationLoaded('stage'), fn (): StageResource => new StageResource($this->stage)),

            /**
             * List of active project members.
             */
            'members' => $this->when($showRoute && $this->relationLoaded('activeMembers'), fn () => InvitedUserResource::collection($this->activeMembers)),

            /**
             * Limited list of recent project activities.
             */
            'activities' => $this->whenLoaded('limitedActivities', fn () => ActivityResource::collection($this->limitedActivities)),

            'links' => $this->when(
                $request->routeIs('projects.update'),
                [
                    'self' => '/api/v1/'.$this->slug,
                ],
            ),
        ];

    }
}
