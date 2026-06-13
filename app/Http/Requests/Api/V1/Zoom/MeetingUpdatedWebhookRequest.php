<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1\Zoom;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class MeetingUpdatedWebhookRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, array<int, string>>
     */
    public function rules(): array
    {
        return [
            'event' => ['required', 'string', Rule::in(['meeting.updated'])],
            'event_ts' => ['sometimes', 'integer'],
            'payload' => ['required', 'array'],
            'payload.object' => ['required', 'array'],
            'payload.object.id' => ['required'],
            'payload.object.topic' => ['sometimes', 'string'],
            'payload.object.start_time' => ['sometimes', 'string'],
            'payload.object.duration' => ['sometimes', 'integer'],
            'payload.object.timezone' => ['sometimes', 'string'],
            'payload.object.uuid' => ['sometimes', 'string'],
            'payload.object.join_url' => ['sometimes', 'string'],
            'payload.object.start_url' => ['sometimes', 'string'],
            'payload.object.password' => ['sometimes', 'nullable', 'string'],
            'payload.object.agenda' => ['sometimes', 'nullable', 'string'],
            'payload.object.settings' => ['sometimes', 'array'],
            'payload.object.settings.join_before_host' => ['sometimes', 'boolean'],
            'payload.object.host_id' => ['sometimes', 'string'],
            'payload.time_stamp' => ['sometimes', 'integer'],
        ];
    }
}
