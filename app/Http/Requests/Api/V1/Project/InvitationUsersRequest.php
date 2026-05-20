<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1\Project;

use Illuminate\Foundation\Http\FormRequest;

class InvitationUsersRequest extends FormRequest
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
            // Accept either a single email or an array of emails.
            'email' => ['sometimes', 'required', 'email'],
            'emails' => ['sometimes', 'array'],
            'emails.*' => ['required', 'email'],
        ];
    }
}
