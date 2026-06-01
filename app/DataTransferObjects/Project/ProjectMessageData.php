<?php

declare(strict_types=1);

namespace App\DataTransferObjects\Project;

final readonly class ProjectMessageData
{
    /**
     * @param  array<int, int>  $recipientIds
     */
    public function __construct(
        public string $message,
        public ?string $subject,
        public bool $mail,
        public bool $sms,
        public ?string $deliveredAt,
        public array $recipientIds,
    ) {}

    /**
     * @param  array<string, mixed>  $payload
     */
    public static function fromArray(array $payload): self
    {
        $deliveredAt = $payload['delivered_at'] ?? null;

        return new self(
            message: (string) ($payload['message'] ?? ''),
            subject: isset($payload['subject']) ? (string) $payload['subject'] : null,
            mail: filter_var($payload['mail'] ?? false, FILTER_VALIDATE_BOOLEAN),
            sms: filter_var($payload['sms'] ?? false, FILTER_VALIDATE_BOOLEAN),
            deliveredAt: is_string($deliveredAt) && $deliveredAt !== '' ? $deliveredAt : null,
            recipientIds: self::extractRecipientIds($payload['users'] ?? []),
        );
    }

    /**
     * @return array{message: string, subject: string|null, mail: bool, sms: bool, delivered_at: string|null, recipient_ids: array<int, int>}
     */
    public function toArray(): array
    {
        return [
            'message' => $this->message,
            'subject' => $this->subject,
            'mail' => $this->mail,
            'sms' => $this->sms,
            'delivered_at' => $this->deliveredAt,
            'recipient_ids' => $this->recipientIds,
        ];
    }

    /**
     * @return array<int, int>
     */
    private static function extractRecipientIds(mixed $users): array
    {
        if (! is_iterable($users)) {
            return [];
        }

        return collect($users)
            ->map(fn (mixed $user): ?int => self::extractRecipientId($user))
            ->filter()
            ->values()
            ->all();
    }

    private static function extractRecipientId(mixed $user): ?int
    {
        $recipientId = match (true) {
            is_array($user) => $user['user_id'] ?? $user['id'] ?? null,
            is_object($user) => $user->user_id ?? $user->id ?? null,
            is_scalar($user) && $user !== '' => $user,
            default => null,
        };

        if ($recipientId === null) {
            return null;
        }

        $normalizedRecipientId = (int) $recipientId;

        return $normalizedRecipientId > 0 ? $normalizedRecipientId : null;
    }
}
