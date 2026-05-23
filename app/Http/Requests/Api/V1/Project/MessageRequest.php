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
            'users' => ['required'], // Accept array or JSON string
            'subject' => ['nullable', 'string', 'max:255'],
            'mail' => ['sometimes'],
            'sms' => ['sometimes'],
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
                $payload['users'] = array_map('trim', explode(',', $payload['users']));
            }
        }

        return ProjectMessageData::fromArray($payload);
    }

    #[Override]
    protected function prepareForValidation(): void
    {
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
