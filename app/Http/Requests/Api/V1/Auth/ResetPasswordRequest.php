<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1\Auth;

use App\DataTransferObjects\Auth\ResetPasswordData;
use Dedoc\Scramble\Attributes\SchemaName;
use Illuminate\Foundation\Http\FormRequest;

#[SchemaName('ResetPasswordRequestData')]
class ResetPasswordRequest extends FormRequest
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
            /**
             * Password reset token received by email.
             *
             * @example 7d2b5c35b1d148f7bba65a19f93ab4fc
             */
            'token' => 'required|string',
            /**
             * Email address tied to the password reset request.
             *
             * @example berry@example.com
             */
            'email' => 'required|email',
            /**
             * New account password.
             * Passwords require letters, mixed case, numbers, and symbols.
             *
             * @example Berry@04
             */
            'password' => RegisterUserRequest::passwordRules(),
        /**
         * Confirmation matching the new password.
         *
         * @example Berry@04
         */
            // `password` rules already include `confirmed` via RegisterUserRequest::passwordRules()
        ];
    }

    public function toDto(): ResetPasswordData
    {
        return ResetPasswordData::fromValidated($this->validated());
    }
}
