<?php

declare(strict_types=1);

namespace App\DataTransferObjects\Zoom;

final readonly class ZoomCallbackData
{
    public function __construct(
        public ?string $code,
        public string $state,
        public ?string $error,
    ) {}

    /**
     * @param  array<string, mixed>  $payload
     */
    public static function fromArray(array $payload): self
    {
        return new self(
            code: isset($payload['code']) ? (string) $payload['code'] : null,
            state: (string) ($payload['state'] ?? ''),
            error: isset($payload['error']) ? (string) $payload['error'] : null,
        );
    }

    /**
     * Convert the DTO to the exact array shape expected by the API.
     *
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'code' => $this->code,
            'state' => $this->state,
            'error' => $this->error,
        ];
    }
}
