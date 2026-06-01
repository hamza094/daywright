<?php

declare(strict_types=1);

namespace App\DataTransferObjects\Notification;

use App\Models\User;

final readonly class NotificationActorData
{
    public function __construct(
        public string $uuid,
        public string $name,
        public ?string $username,
        public ?string $avatarPath,
        public string $email,
    ) {}

    public static function fromUser(User $user): self
    {
        return new self(
            uuid: $user->uuid,
            name: $user->name,
            username: $user->username,
            avatarPath: $user->avatar_path,
            email: $user->email,
        );
    }

    /**
     * @return array{uuid: string, name: string, username: string|null, avatar_path: string|null, email: string}
     */
    public function toArray(): array
    {
        return [
            'uuid' => $this->uuid,
            'name' => $this->name,
            'username' => $this->username,
            'avatar_path' => $this->avatarPath,
            'email' => $this->email,
        ];
    }
}
