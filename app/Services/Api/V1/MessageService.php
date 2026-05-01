<?php

declare(strict_types=1);

namespace App\Services\Api\V1;

use App\Models\Message;
use App\Models\Project;
use Illuminate\Bus\Batch;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Bus;
use Illuminate\Validation\ValidationException;
use Throwable;

class MessageService
{
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

            $message = $this->createMessage($project, $type, $users, $payload);
            $this->sendOrScheduleMessage($project, $message, $payload);
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

    /**
     * @param  Collection<int, int|string>  $users
     * @param  array<string, mixed>  $payload
     */
    public function createMessage(Project $project, string $type, Collection $users, array $payload): Message
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

    /**
     * @param  array<string, mixed>  $payload
     */
    public function sendOrScheduleMessage(Project $project, Message $message, array $payload): void
    {
        ! empty($payload['delivered_at'])
            ? $this->scheduledMessage($message, $payload)
            : $this->sendNow($project, $message);
    }

    public function sendNow(Project $project, Message $message): void
    {
        $message->type === 'mail' ? $job = \App\Jobs\MailMessage::class :
        $job = \App\Jobs\SmsMessage::class;

        $jobs = $message->users
            ->map(fn ($user): \App\Jobs\MailMessage|\App\Jobs\SmsMessage => new $job($project, $message, $user));

        $batch = Bus::batch($jobs)
            ->allowFailures()
            ->then(function () use ($message): void {
                $message->delivered = true;
                $message->save();
                // notify user on batch success
            })->catch(function (Batch $batch, Throwable $e): void {
                // notify on job failure
            })->dispatch();

        $message->update([
            'batch_id' => $batch->id,
        ]);
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    public function scheduledMessage(Message $message, array $payload): void
    {
        $this->saveScheduledDeliveryAt($message, (string) ($payload['delivered_at'] ?? ''));
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

    private function saveScheduledDeliveryAt(Message $message, string $deliveredAt): void
    {
        $message->delivered_at = $deliveredAt;
        $message->save();
    }
}
