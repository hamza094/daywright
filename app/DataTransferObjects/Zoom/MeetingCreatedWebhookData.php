<?php

declare(strict_types=1);

namespace App\DataTransferObjects\Zoom;

final readonly class MeetingCreatedWebhookData
{
    public function __construct(
        public int|string $meetingId,
        public ?string $operationId,
        public ?string $joinUrl,
        public ?string $creationSource,
        public ?string $requestId,
    ) {}

    /**
     * @param  array<string, mixed>  $payload
     */
    public static function fromPayloadObject(array $payload, ?string $requestId, string $trackingFieldName): self
    {
        $operationId = null;

        if (isset($payload['tracking_fields']) && is_array($payload['tracking_fields'])) {
            foreach ($payload['tracking_fields'] as $field) {
                if (isset($field['field'], $field['value']) && $field['field'] === $trackingFieldName && is_string($field['value'])) {
                    $operationId = $field['value'];
                    break;
                }
            }
        }

        return new self(
            meetingId: is_int($payload['id'] ?? null) || is_string($payload['id'] ?? null) ? $payload['id'] : 0,
            operationId: $operationId,
            joinUrl: $payload['join_url'] ?? null,
            creationSource: $payload['creation_source'] ?? null,
            requestId: $requestId,
        );
    }

    /**
     * @param  array{meetingId: int|string, operationId: ?string, joinUrl: ?string, creationSource: ?string, requestId: ?string}  $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            meetingId: $data['meetingId'],
            operationId: $data['operationId'] ?? null,
            joinUrl: $data['joinUrl'] ?? null,
            creationSource: $data['creationSource'] ?? null,
            requestId: $data['requestId'] ?? null,
        );
    }

    /**
     * @return array{meetingId: int|string, operationId: ?string, joinUrl: ?string, creationSource: ?string, requestId: ?string}
     */
    public function toArray(): array
    {
        return [
            'meetingId' => $this->meetingId,
            'operationId' => $this->operationId,
            'joinUrl' => $this->joinUrl,
            'creationSource' => $this->creationSource,
            'requestId' => $this->requestId,
        ];
    }
}
