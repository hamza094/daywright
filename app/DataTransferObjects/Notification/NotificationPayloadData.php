<?php

declare(strict_types=1);

namespace App\DataTransferObjects\Notification;

final readonly class NotificationPayloadData
{
    public function __construct(
        public string $message,
        public ?NotificationActorData $notifier,
        public string $link,
    ) {}

    /**
     * @param  array<string, mixed>  $payload
     */
    public static function fromArray(array $payload): self
    {
        return new self(
            message: (string) ($payload['message'] ?? ''),
            notifier: self::resolveNotifier($payload['notifier'] ?? null),
            link: (string) ($payload['link'] ?? ''),
        );
    }

    /**
     * @return array{message: string, notifier: array{uuid: string, name: string, username: string|null, avatar_path: string|null, email: string}|null, link: string}
     */
    public function toArray(): array
    {
        return [
            'message' => $this->message,
            'notifier' => $this->notifier?->toArray(),
            'link' => $this->link,
        ];
    }

    private static function resolveNotifier(mixed $notifier): ?NotificationActorData
    {
        if (is_object($notifier)) {
            $notifier = get_object_vars($notifier);
        }

        if (! is_array($notifier)) {
            return null;
        }

        $uuid = $notifier['uuid'] ?? null;
        $name = $notifier['name'] ?? null;
        $email = $notifier['email'] ?? null;

        if (! is_string($uuid) || ! is_string($name) || ! is_string($email)) {
            return null;
        }

        $username = $notifier['username'] ?? null;
        $avatarPath = $notifier['avatar_path'] ?? $notifier['avatarPath'] ?? null;

        return new NotificationActorData(
            uuid: $uuid,
            name: $name,
            username: is_string($username) ? $username : null,
            avatarPath: is_string($avatarPath) ? $avatarPath : null,
            email: $email,
        );
    }
}
