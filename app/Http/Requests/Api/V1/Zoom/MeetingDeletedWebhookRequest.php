<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1\Zoom;

use App\DataTransferObjects\Zoom\MeetingDeletedWebhookData;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class MeetingDeletedWebhookRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    public function toDto(): MeetingDeletedWebhookData
    {
        /** @var array<string, mixed> $validated */
        $validated = $this->validated();

        /** @var array<string, mixed> $payloadObject */
        $payloadObject = $validated['payload']['object'] ?? [];

        return MeetingDeletedWebhookData::fromPayloadObject(
            $payloadObject,
            $this->header('x-zm-request-id')
        );
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, list<string|\Illuminate\Validation\Rules\In>>
     */
    public function rules(): array
    {
        return [
            'event' => ['required', 'string', Rule::in(['meeting.deleted'])],
            'event_ts' => ['sometimes', 'integer'],
            'payload' => ['required', 'array'],
            'payload.object' => ['required', 'array'],
            'payload.object.id' => ['required', 'numeric'],
        ];
    }
}
