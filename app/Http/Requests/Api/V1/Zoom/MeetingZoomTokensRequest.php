<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1\Zoom;

use App\DataTransferObjects\Zoom\MeetingZoomTokenData;
use Illuminate\Foundation\Http\FormRequest;

class MeetingZoomTokensRequest extends FormRequest
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
            'action' => 'required|in:start,join',
        ];
    }

    public function toDto(): MeetingZoomTokenData
    {
        return MeetingZoomTokenData::fromValidated($this->validated());
    }
}
