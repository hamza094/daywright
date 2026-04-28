<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Api\ApiController;
use App\Http\Requests\Api\V1\MessageRequest;
use App\Models\Message;
use App\Models\Project;
use App\Services\Api\V1\MessageService;
use F9Web\ApiResponseHelpers;

class MessageController extends ApiController
{
    use ApiResponseHelpers;

    public function message(Project $project, MessageRequest $request, MessageService $message)
    {
        $message->checkOptionSelect($request);

        $users = collect($request->users)->filter(fn ($user): bool => ! empty($user['user_id']))->pluck('user_id');

        return $message->send($project, $users);
    }

    public function scheduled(Project $project): \Illuminate\Http\JsonResponse|\Illuminate\Support\Collection
    {

        if ($project->scheduledMessages()->isEmpty()) {

            return $this->respondNoContent([
                'message' => 'No project schedule messages found',
            ]);

        }

        return $project->scheduledMessages();
    }

    public function delete(Project $project, Message $message): \Illuminate\Http\JsonResponse
    {

        $message->activities()->delete();

        $message->delete();

        return $this->respondNoContent([
            'message' => 'Scheduled message deleted Successfully',
        ]);
    }
}
