<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1\Auth;

use Dedoc\Scramble\Attributes\SchemaName;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Password;
use Override;

#[SchemaName('RegisterRequestData')]
class RegisterUserRequest extends FormRequest
{
    /**
     * Centralized password validation rules for reuse across auth flows.
     *
     * @return array<int, string|\Illuminate\Contracts\Validation\Rule>
     */
    public static function passwordRules(): array
    {
        return [
            'required',
            'string',
            'confirmed',
            Password::default(),
        ];
    }

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
            /**
             * Display name shown across the application.
             *
             * @example Berry
             */
            'name' => 'required|string|max:100',
            /**
             * Email address used for login, notifications, and verification.
             *
             * @example berry@example.com
             */
            'email' => 'required|string|email|max:255|unique:users',
            /**
             * Password used for future token and session logins.
             * Passwords require letters, mixed case, numbers, and symbols.
             * Submit a matching `password_confirmation` field alongside this value.
             *
             * @example Berry@04
             */
            'password' => self::passwordRules(),
        ];
    }

    /**
     * @return array<string, string>
     */
    #[Override]
    public function messages(): array
    {
        return [
            'password.mixed' => 'The password must include both uppercase and lowercase letters.',
            'password.letters' => 'The password must contain at least one letter.',
            'password.symbols' => 'The password must include at least one special character (symbol).',
            'password.numbers' => 'The password must contain at least one number.',
        ];
    }
}
