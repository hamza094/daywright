<?php

declare(strict_types=1);

namespace App\Actions\Project;

use App\Models\Message;
use App\Models\Project;
use Illuminate\Support\Collection;

final class CreateProjectMessageAction
{
    /**
     * @param  Collection<int, int|string>  $users
     * @param  array<string, mixed>  $payload
     */
    public function execute(Project $project, string $type, Collection $users, array $payload): Message
    {
        $message = Message::create([
            'project_id' => $project->id,
            'type' => $type,
            'message' => (string) ($payload['message'] ?? ''),
        ]);

        if ($message->type === 'mail') {
            $message->subject = isset($payload['subject']) ? (string) $payload['subject'] : null;
            $message->save();
        }

        $message->users()->attach($users);

        return $message;
    }
}
