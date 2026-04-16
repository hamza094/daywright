<?php

declare(strict_types=1);

namespace App\Services\Api\V1;

use App\Actions\BuildPaginatedPayloadAction;
use App\Http\Resources\Api\V1\Zoom\MeetingResource;
use App\Models\Project;

class MeetingService
{
    public function __construct(private readonly BuildPaginatedPayloadAction $buildPaginatedPayloadAction) {}

    public function getMeetingsData(Project $project, bool $isPrevious): array
    {
        $meetingsQuery = $project->meetings()->with('user');

        $meetingsQuery->when($isPrevious, fn ($query) => $query->previous(), fn ($query) => $query->scheduled());

        $paginator = $meetingsQuery->paginate(3);

        $message = $paginator->isEmpty()
            ? 'Sorry, no meetings found.'
            : $this->getMessage($isPrevious);

        $meetingsData = $this->buildPaginatedPayloadAction->handle($paginator, MeetingResource::class);

        return ['message' => $message, 'meetingsData' => $meetingsData];
    }

    private function getMessage(bool $isPrevious): string
    {
        return $isPrevious ? 'Previous meetings' : 'Scheduled meetings';
    }
}
