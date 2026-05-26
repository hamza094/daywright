<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1\Zoom;

use Illuminate\Foundation\Http\FormRequest;

class JwtTokenRequest extends FormRequest
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
            'role' => 'required|in:0,1',
            'meeting_id' => 'required|integer',
        ];
    }

    protected function prepareForValidation(): void
    {
        // Accept legacy camelCase `meetingId` but normalize to snake_case
        if ($this->has('meetingId') && ! $this->has('meeting_id')) {
            $this->merge(['meeting_id' => $this->input('meetingId')]);
        }
    }
}
