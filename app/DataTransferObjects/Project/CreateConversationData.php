<?php

declare(strict_types=1);

namespace App\DataTransferObjects\Project;

use Illuminate\Http\UploadedFile;
use InvalidArgumentException;

final readonly class CreateConversationData
{
    public function __construct(
        public ?string $message,
        public UploadedFile|string|null $file,
    ) {}

    /**
     * @param  array<string, mixed>  $payload
     */
    public static function fromArray(array $payload): self
    {
        $message = $payload['message'] ?? null;
        $file = $payload['file'] ?? null;

        $normalizedMessage = is_string($message) && $message !== '' ? $message : null;
        $normalizedFile = ($file instanceof UploadedFile || (is_string($file) && $file !== '')) ? $file : null;

        if ($normalizedMessage === null && $normalizedFile === null) {
            throw new InvalidArgumentException('A conversation must have a message or a file.');
        }

        return new self(
            message: $normalizedMessage,
            file: $normalizedFile,
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
