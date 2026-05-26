<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1\Project;

use App\DataTransferObjects\Project\ProjectMessageData;
use App\Rules\Iso8601Timestamp;
use Dedoc\Scramble\Attributes\SchemaName;
use Illuminate\Foundation\Http\FormRequest;
use Override;

#[SchemaName('ProjectMessageStoreRequestData')]
class MessageRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'message' => ['required_without:file', 'string', 'min:1', 'max:2000'],
            'file' => ['required_without:message', 'file', 'max:700', 'mimes:jpg,png,pdf,docx'],
            'users' => ['required', 'array', 'min:1'], // Accept array or JSON string
            'users.*' => ['integer', 'exists:users,id'],
            'subject' => ['nullable', 'string', 'max:255'],
            'mail' => ['sometimes', 'boolean'],
            'sms' => ['sometimes', 'boolean'],
            'delivered_at' => ['sometimes', 'nullable', 'bail', 'string', new Iso8601Timestamp],
            'date' => ['prohibited'],
            'time' => ['prohibited'],
        ];
    }

    /**
     * Convert the request into a ProjectMessageData DTO.
     */
    public function messageData(): ProjectMessageData
    {
        $payload = $this->validated();

        if (isset($payload['users']) && is_string($payload['users'])) {
            // @phpstan-ignore-next-line - decoding user-provided JSON; fall back to CSV on error
            $decoded = json_decode($payload['users'], true);

            if (json_last_error() === JSON_ERROR_NONE) {
                $payload['users'] = $decoded;
            } else {
                $payload['users'] = array_map(trim(...), explode(',', $payload['users']));
            }
        }

        return ProjectMessageData::fromArray($payload);
    }

    #[Override]
    protected function prepareForValidation(): void
    {
        // Normalize users if provided as a JSON string or CSV
        if ($this->has('users') && is_string($this->input('users'))) {
            $raw = $this->input('users');

            $decoded = json_decode($raw, true);

            if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
                $normalized = $decoded;
            } else {
                $items = array_map('trim', explode(',', (string) $raw));
                $normalized = array_values(array_filter($items, fn ($v) => $v !== ''));
            }

            $this->merge(['users' => $normalized]);
        }

        if (! $this->has('delivered_at')) {
            return;
        }

        $normalizedDeliveredAt = Iso8601Timestamp::normalizeToUtc((string) $this->input('delivered_at'));

        if ($normalizedDeliveredAt === null) {
            return;
        }

        $this->merge([
            'delivered_at' => $normalizedDeliveredAt,
        ]);
    }
}
