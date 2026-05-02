<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Project;

use App\Http\Controllers\Api\ApiController;
use App\Models\Project;
use App\Services\Api\V1\MessageService;
use Illuminate\Support\Collection;

final class ScheduledProjectMessagesController extends ApiController
{
    public function __invoke(Project $project, MessageService $messageService): Collection
    {
        return $messageService->scheduledMessages($project);
    }
}
