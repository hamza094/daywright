<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1\Zoom;

use App\Rules\Iso8601Timestamp;
use Illuminate\Foundation\Http\FormRequest;
use Override;

class MeetingStoreRequest extends FormRequest
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
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'topic' => 'required|max:200|string',
            'agenda' => 'required|max:2000|string',
            'duration' => 'required|integer|min:1',
            'start_time' => [
                'required',
                'bail',
                'string',
                new Iso8601Timestamp,
                'after:now',
            ],
            'timezone' => 'required|timezone:all|string',
            'password' => 'required|max:10|string',
            'join_before_host' => 'required|boolean',
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
