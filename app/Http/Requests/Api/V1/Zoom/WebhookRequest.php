<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1\Zoom;

use Illuminate\Foundation\Http\FormRequest;

class WebhookRequest extends FormRequest
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
     * @return array<string, string>
     */
    public function rules(): array
    {
        return [
            'event' => ['required', 'string'],
            'event_ts' => ['sometimes', 'integer'],
            'payload' => ['required', 'array'],
            'payload.object' => ['required', 'array'],
            'payload.object.id' => ['required'],
            'payload.object.start_time' => ['sometimes', 'string'],
            'payload.object.end_time' => ['sometimes', 'string'],
            'payload.object.uuid' => ['sometimes', 'string'],
        ];
    }
}
