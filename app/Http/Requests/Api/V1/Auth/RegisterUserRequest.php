<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1\Auth;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Password;
use Override;

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
             * @example berry
             */
            'name' => 'required|string|max:100',
            'email' => 'required|string|email|max:255|unique:users',
            /**
             * Passwords require letters, mixed case,  numbers, and symbols.
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
    public function messages()
    {
        return [
            'password.mixed' => 'The password must include both uppercase and lowercase letters.',
            'password.letters' => 'The password must contain at least one letter.',
            'password.symbols' => 'The password must include at least one special character (symbol).',
            'password.numbers' => 'The password must contain at least one number.',
        ];
    }
}
