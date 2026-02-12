<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Password;

class UserRequest extends FormRequest
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
            /*
             * @example "John Doe"
             */
            'name' => ['sometimes', 'required', 'string', 'max:30'],
            /*
             * @example "john.doe@example.com"
             */
            'email' => ['sometimes', 'required', 'email', 'max:100'],
            /*
             * @example "john_doe"
             */
            'username' => ['sometimes', 'required', 'alpha_dash:ascii', 'max:30'],
            /*
             * @example "1234567890"
             */
            'mobile' => ['sometimes', 'nullable', 'digits_between:7,15'],
            /*
             * @example "Company Name"
             */
            'company' => ['sometimes', 'nullable', 'string', 'max:100'],
            /*
             * @example "Use detailed bio"
             */
            'bio' => ['sometimes', 'nullable', 'string', 'max:1500'],
            /*
             * @example "123 Main St, City, Country"
             */
            'address' => ['sometimes', 'nullable', 'string', 'max:150'],
            /*
             * @example "Software Engineer"
             */
            'position' => ['sometimes', 'nullable', 'string', 'max:100'],
            /*
             * @example "America/New_York"
             */
            'timezone' => ['sometimes', 'nullable', 'string', 'timezone:all'],
            /*
             * Current password is required when updating the `password`
             * and must match the user's current password.
             */

            'current_password' => ['sometimes', 'current_password', 'required_with:password'],
            /*
             * Required with `current_password`.
             *
             *  Password rules:
             * - At least 8 characters
             * - At least one uppercase letter
             * - At least one lowercase letter
             * - At least one number
             * - At least one special character
             */
            'password' => ['sometimes', 'required_with:current_password', Password::default()],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages()
    {
        return [
            'current_password.current_password' => 'The given password does not match to current password.',
            'password.mixed' => 'The password must include both uppercase and lowercase letters.',
            'password.letters' => 'The password must contain at least one letter.',
            'password.symbols' => 'The password must include at least one special character (symbol).',
            'password.numbers' => 'The password must contain at least one number.',
        ];
    }
}
