<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1\Project;

use App\DataTransferObjects\Project\InvitationData;
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
            /**
             * Email address of the user to invite to the project.
             *
             * @example john.doe@example.com
             */
            'email' => ['required', 'email'],
        ];
    }
}
