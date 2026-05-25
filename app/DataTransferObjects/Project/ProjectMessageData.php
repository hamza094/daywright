<?php

declare(strict_types=1);

namespace App\DataTransferObjects\Project;

final readonly class ProjectMessageData
{
    /**
     * @param  array<int, int|string>  $recipientIds
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
     * @return array{message: string, subject: string|null, mail: bool, sms: bool, delivered_at: string|null, recipient_ids: array<int, int|string>}
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
     * @return array<int, int|string>
     */
    private static function extractRecipientIds(mixed $users): array
    {
        /** @phpstan-ignore-next-line */
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
            ->filter(fn (mixed $userId): bool => $userId !== 0 && ($userId !== '' && $userId !== '0'))
            ->values()
            ->all();
    }
}
