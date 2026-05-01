<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1\Zoom;

use App\Rules\Iso8601Timestamp;
use Illuminate\Foundation\Http\FormRequest;
use Override;

class MeetingUpdateRequest extends FormRequest
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
            'meeting_id' => 'integer|required',
            'topic' => 'string|max:200|sometimes',
            'agenda' => 'string|sometimes|max:2000',
            'duration' => 'integer|sometimes',
            'start_time' => [
                'sometimes',
                'bail',
                'string',
                new Iso8601Timestamp,
                'after:now',
            ],
            'timezone' => 'string|timezone:all|sometimes',
            'password' => 'string|max:10|sometimes',
            'join_before_host' => 'boolean|sometimes',
        ];
    }

    #[Override]
    protected function prepareForValidation(): void
    {
        if (! $this->has('start_time')) {
            return;
        }

        $normalizedStartTime = Iso8601Timestamp::normalizeToUtc((string) $this->input('start_time'));

        if ($normalizedStartTime === null) {
            return;
        }

        $this->merge([
            'start_time' => $normalizedStartTime,
        ]);
    }
}
