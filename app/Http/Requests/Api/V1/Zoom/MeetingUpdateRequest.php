<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1\Zoom;

use Exception;
use Illuminate\Foundation\Http\FormRequest;
use Override;
use Safe\DateTimeImmutable;

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
            'start_time' => 'sometimes|after:now',
            'timezone' => 'string|timezone:all|sometimes',
            'password' => 'string|max:10|sometimes',
            'join_before_host' => 'boolean|sometimes',
        ];
    }

    #[Override]
    protected function prepareForValidation()
    {
        if ($this->has('start_time')) {
            try {
                $this->merge([
                    'start_time' => (new DateTimeImmutable($this->input('start_time')))->format('Y-m-d H:i:s'),
                ]);
            } catch (Exception) {
                $this->merge([
                    'start_time' => null,
                ]);
            }
        }
    }
}
