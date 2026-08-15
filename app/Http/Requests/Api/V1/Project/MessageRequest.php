<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1\Project;

use App\DataTransferObjects\Project\ProjectMessageData;
use App\Rules\Iso8601Timestamp;
use Dedoc\Scramble\Attributes\SchemaName;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;
use Override;
use Safe\Exceptions\JsonException;

use function Safe\json_decode;

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
            'mail' => ['sometimes', 'nullable', 'boolean'],
            'sms' => ['sometimes', 'nullable', 'boolean'],
            'delivered_at' => ['sometimes', 'nullable', 'bail', 'string', new Iso8601Timestamp],
            'date' => ['prohibited'],
            'time' => ['prohibited'],
        ];
    }

    /**
     * Convert the request into a ProjectMessageData DTO.
     */
    public function toDto(): ProjectMessageData
    {
        return ProjectMessageData::fromArray($this->validated());
    }

    protected function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            if ($this->deliveryOptionSelected()) {
                return;
            }

            $validator->errors()->add('option', 'Please choose any options.');
        });
    }

    #[Override]
    protected function prepareForValidation(): void
    {
        // Normalize users if provided as a JSON string or CSV
        if ($this->has('users') && is_string($this->input('users'))) {
            $raw = $this->input('users');

            try {
                $decoded = json_decode($raw, true);
            } catch (JsonException) {
                $decoded = null;
            }

            if (is_array($decoded)) {
                $normalized = $decoded;
            } else {
                $items = array_map(trim(...), explode(',', $raw));
                $normalized = array_values(array_filter($items, fn ($v): bool => $v !== ''));
            }

            $this->merge(['users' => $normalized]);
        }

        if ($this->has('users') && is_array($this->input('users'))) {
            $this->merge([
                'users' => $this->normalizeUsers($this->input('users')),
            ]);
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

    private function deliveryOptionSelected(): bool
    {
        return filter_var($this->input('mail', false), FILTER_VALIDATE_BOOLEAN)
            || filter_var($this->input('sms', false), FILTER_VALIDATE_BOOLEAN);
    }

    /**
     * @param  array<int, mixed>  $users
     * @return array<int, int|string>
     */
    private function normalizeUsers(array $users): array
    {
        return array_values(array_filter(array_map(function (mixed $user): int|string|null {
            if (is_array($user)) {
                return $user['user_id'] ?? $user['id'] ?? null;
            }

            return is_scalar($user) ? $user : null;
        }, $users), fn (mixed $userId): bool => $userId !== null && $userId !== ''));
    }
}
