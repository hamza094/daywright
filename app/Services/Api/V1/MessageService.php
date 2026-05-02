<?php

declare(strict_types=1);

namespace App\Services\Api\V1;

use App\Actions\Project\CreateProjectMessageAction;
use App\Actions\Project\DispatchProjectMessageAction;
use App\Actions\Project\ScheduleProjectMessageAction;
use App\Models\Message;
use App\Models\Project;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;

class MessageService
{
    public function __construct(
        private readonly CreateProjectMessageAction $createProjectMessageAction,
        private readonly DispatchProjectMessageAction $dispatchProjectMessageAction,
        private readonly ScheduleProjectMessageAction $scheduleProjectMessageAction,
    ) {}

    /**
     * @param  array<string, mixed>  $payload
     */
    public function send(Project $project, array $payload): string
    {
        $this->ensureDeliveryOptionSelected($payload);

        $users = $this->extractRecipientIds($payload['users'] ?? []);
        $isScheduled = ! empty($payload['delivered_at']);

        foreach (['mail', 'sms'] as $type) {
            if (! $this->deliveryOptionEnabled($payload, $type)) {
                continue;
            }

            $message = $this->createProjectMessageAction->execute($project, $type, $users, $payload);

            if ($isScheduled) {
                $this->scheduleProjectMessageAction->execute($message, (string) $payload['delivered_at']);

                continue;
            }

            $this->dispatchProjectMessageAction->execute($project, $message);
        }

        return 'Messages '.($isScheduled ? 'Scheduled' : 'Sent').' Successfully';
    }

    /**
     * @return Collection<int, mixed>
     */
    public function scheduledMessages(Project $project): Collection
    {
        return $project->scheduledMessages();
    }

    public function deleteScheduledMessage(Message $message): void
    {
        $message->activities()->delete();
        $message->delete();
    }

    public function sendNow(Project $project, Message $message): void
    {
        $this->dispatchProjectMessageAction->execute($project, $message);
    }

    /**
     * @return Collection<int, int|string>
     */
    public function extractRecipientIds(mixed $users): Collection
    {
        return collect($users)
            ->map(function (mixed $user): int|string|null {
                if (is_array($user)) {
                    return $user['user_id'] ?? $user['id'] ?? null;
                }

                if (is_object($user)) {
                    return $user->user_id ?? $user->id ?? null;
                }

                return is_scalar($user) && $user !== '' ? $user : null;
            })
            ->filter(fn (mixed $userId): bool => ! empty($userId))
            ->values();
    }

    private function ensureDeliveryOptionSelected(array $payload): void
    {
        if ($this->deliveryOptionEnabled($payload, 'mail') || $this->deliveryOptionEnabled($payload, 'sms')) {
            return;
        }

        throw ValidationException::withMessages([
            'option' => 'Please choose any options.',
        ]);
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function deliveryOptionEnabled(array $payload, string $type): bool
    {
        return filter_var($payload[$type] ?? false, FILTER_VALIDATE_BOOLEAN);
    }
}
