<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1\Zoom;

use App\DataTransferObjects\Zoom\MeetingCreatedWebhookData;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class MeetingCreatedWebhookRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    public function toDto(): MeetingCreatedWebhookData
    {
        /** @var array<string, mixed> $validated */
        $validated = $this->validated();

        /** @var array<string, mixed> $payloadObject */
        $payloadObject = $validated['payload']['object'] ?? [];

        return MeetingCreatedWebhookData::fromPayloadObject(
            $payloadObject,
            $this->header('x-zm-request-id'),
            (string) config('services.zoom.meeting_operation_tracking_field', 'Daywright Operation ID'),
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
            'event' => ['required', 'string', Rule::in(['meeting.created'])],
            'event_ts' => ['sometimes', 'integer'],
            'payload' => ['required', 'array'],
            'payload.object' => ['required', 'array'],
            'payload.object.id' => ['required', 'numeric'],
            'payload.object.creation_source' => ['sometimes', 'string'],
            'payload.object.tracking_fields' => ['sometimes', 'array'],
            'payload.object.tracking_fields.*.field' => ['sometimes', 'string'],
            'payload.object.tracking_fields.*.value' => ['sometimes', 'string'],
        ];
    }
}
