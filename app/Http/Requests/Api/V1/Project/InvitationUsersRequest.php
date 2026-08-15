<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1\Project;

use App\DataTransferObjects\Project\InvitationData;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class InvitationUsersRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    public function toDto(): InvitationData
    {
        return InvitationData::fromArray($this->validated());
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

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            if (! $this->has('email') && ! $this->has('emails')) {
                $validator->errors()->add('email', 'Provide at least one email address to invite.');
            }
        });
    }
}
