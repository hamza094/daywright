<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1\Zoom;

use Illuminate\Foundation\Http\FormRequest;

final class ZoomAuthorizationCallbackRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'code' => ['required_without:error', 'string'],
            'state' => ['required', 'string'],
            'error' => ['sometimes', 'string'],
        ];
    }
}
