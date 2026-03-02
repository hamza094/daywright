<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1\Zoom;

use Exception;
use Illuminate\Foundation\Http\FormRequest;
use Override;
use Safe\DateTimeImmutable;

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
     * @return array<string, string>
     */
    public function rules(): array
    {
        return [
            'topic' => 'required|max:200|string',
            'agenda' => 'required|max:2000|string',
            'duration' => 'required|integer',
            'start_time' => 'required|after:now',
            'timezone' => 'required|timezone:all|string',
            'password' => 'required|max:10|string',
            'join_before_host' => 'required|boolean',
        ];
    }

    #[Override]
    protected function prepareForValidation()
    {
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
