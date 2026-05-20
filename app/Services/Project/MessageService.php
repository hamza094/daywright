<?php

declare(strict_types=1);

namespace App\Services\Project;

use App\Actions\Project\CreateProjectMessageAction;
use App\Actions\Project\DispatchProjectMessageAction;
use App\Actions\Project\ScheduleProjectMessageAction;
use App\DataTransferObjects\Project\ProjectMessageData;
use App\Models\Message;
use App\Models\Project;
use App\Repository\Api\V1\MessageRepository;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Validation\ValidationException;

class MessageService
{
    public function __construct(
        private readonly CreateProjectMessageAction $createProjectMessageAction,
        private readonly DispatchProjectMessageAction $dispatchProjectMessageAction,
        private readonly ScheduleProjectMessageAction $scheduleProjectMessageAction,
        private readonly MessageRepository $messageRepository,
    ) {}

    public function send(Project $project, ProjectMessageData $payload): string
    {
        $this->ensureDeliveryOptionSelected($payload);

        $isScheduled = $payload->deliveredAt !== null;

        foreach (['mail', 'sms'] as $type) {
            if (! $this->deliveryOptionEnabled($payload, $type)) {
                continue;
            }

            $message = $this->createProjectMessageAction->execute($project, $type, $payload);

            if ($isScheduled && $payload->deliveredAt !== null) {
                $this->scheduleProjectMessageAction->execute($message, $payload->deliveredAt);

                continue;
            }

            $this->dispatchProjectMessageAction->execute($project, $message);
        }

        return 'Messages '.($isScheduled ? 'Scheduled' : 'Sent').' Successfully';
    }

    public function paginateScheduledMessages(Project $project, int $perPage, int $page): LengthAwarePaginator
    {
        return $this->messageRepository->paginateScheduledMessages($project, $perPage, $page);
    }

    public function deleteScheduledMessage(Message $message): void
    {
        $message->activities()->delete();
        $message->delete();
    }

    public function sendNow(Project $project, Message $message): bool
    {
        return $this->dispatchProjectMessageAction->execute($project, $message);
    }

    private function ensureDeliveryOptionSelected(ProjectMessageData $payload): void
    {
        if ($payload->mail || $payload->sms) {
            return;
        }

        throw ValidationException::withMessages([
            'option' => 'Please choose any options.',
        ]);
    }

    private function deliveryOptionEnabled(ProjectMessageData $payload, string $type): bool
    {
        return match ($type) {
            'mail' => $payload->mail,
            'sms' => $payload->sms,
            default => false,
        };
    }
}
