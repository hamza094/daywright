<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1;

use Dedoc\Scramble\Attributes\SchemaName;
use Illuminate\Foundation\Http\FormRequest;
use Override;

#[SchemaName('ProjectInvitationStoreRequestData')]
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
     * Get the validation rules that apply to the request.
     *
     * @return array<string, string>
     */
    public function rules(): array
    {
        return [
            /**
             * Email address of the existing user to invite to the project.
             * The email must already belong to a registered user.
             *
             * @example maximillia.koelpin@example.com
             */
            'email' => 'required|email|exists:users,email',
        ];
    }

    /**
     * Custom error messages for validation.
     *
     * @return array<string, string>
     */
    #[Override]
    public function messages()
    {
        return [
            'email.exists' => 'The email does not belong to any registered user.',
        ];
    }
}
