<?php

declare(strict_types=1);

namespace App\Actions\Project;

use App\DataTransferObjects\Project\ProjectMessageData;
use App\Models\Message;
use App\Models\Project;
use Illuminate\Support\Facades\DB;

final class CreateProjectMessageAction
{
    public function execute(Project $project, string $type, ProjectMessageData $payload): Message
    {
        return DB::transaction(function () use ($project, $type, $payload): Message {
            $message = Message::create([
                'project_id' => $project->id,
                'type' => $type,
                'message' => $payload->message,
                'subject' => $type === 'mail'
                    ? $payload->subject
                    : null,
            ]);

            $message->users()->syncWithoutDetaching($payload->recipientIds);

            return $message;
        });
    }
}
