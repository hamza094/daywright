<?php

declare(strict_types=1);

namespace App\DataTransferObjects\Project;

final readonly class CreateConversationData
{
    public function __construct(
        public ?string $message,
        public ?string $file,
    ) {}

    /**
     * @param  array<string, mixed>  $payload
     */
    public static function fromArray(array $payload): self
    {
        $message = $payload['message'] ?? null;
        $file = $payload['file'] ?? null;

        return new self(
            message: is_string($message) && $message !== '' ? $message : null,
            file: is_string($file) && $file !== '' ? $file : null,
        );
    }

    public function withStoredFile(?string $file): self
    {
        return new self(
            message: $this->message,
            file: $file,
        );
    }

    /**
     * @return array{message?: string, file?: string}
     */
    public function toArray(): array
    {
        return array_filter([
            'message' => $this->message,
            'file' => $this->file,
        ], static fn (mixed $value): bool => $value !== null);
    }
}
