<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1\User;

use App\DataTransferObjects\User\UpdateUserData;
use Dedoc\Scramble\Attributes\SchemaName;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;
use Override;

#[SchemaName('UserUpdateRequestData')]
class UserRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    public function toDto(): UpdateUserData
    {
        /** @var array<string, mixed> $validated */
        $validated = $this->validated();

        return UpdateUserData::fromArray($validated);
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
             * Updated display name.
             *
             * @example John Doe
             */
            'name' => ['sometimes', 'required', 'string', 'max:30'],
            /**
             * Updated primary email address.
             *
             * @example john.doe@example.com
             */
            'email' => ['sometimes', 'required', 'email', 'max:100', Rule::unique('users')->ignore($this->user())],
            /**
             * Updated public username.
             *
             * @example john_doe
             */
            'username' => ['sometimes', 'required', 'alpha_dash:ascii', 'max:30', Rule::unique('users')->ignore($this->user())],
            /**
             * Optional mobile number.
             *
             * @example 1234567890
             */
            'mobile' => ['sometimes', 'nullable', 'digits_between:7,15'],
            /**
             * Optional company name.
             *
             * @example Company Name
             */
            'company' => ['sometimes', 'nullable', 'string', 'max:100'],
            /**
             * Optional biography shown on the profile.
             *
             * @example Product designer and async collaboration enthusiast.
             */
            'bio' => ['sometimes', 'nullable', 'string', 'max:1500'],
            /**
             * Optional mailing or profile address.
             *
             * @example 123 Main St, City, Country
             */
            'address' => ['sometimes', 'nullable', 'string', 'max:150'],
            /**
             * Optional role or job title.
             *
             * @example Software Engineer
             */
            'position' => ['sometimes', 'nullable', 'string', 'max:100'],
            /**
             * Optional IANA timezone identifier.
             *
             * @example America/New_York
             */
            'timezone' => ['sometimes', 'nullable', 'string', 'timezone:all'],
            /**
             * Current password required when changing the password.
             */
            'current_password' => ['sometimes', 'current_password', 'required_with:password'],
            /**
             * New password. Submit a matching `password_confirmation` field alongside this value.
             *
             * @example Password4!
             */
            'password' => ['sometimes', 'required_with:current_password', Password::default(), 'confirmed'],
        ];
    }

    /**
     * @return array<string, string>
     */
    #[Override]
    public function messages(): array
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
