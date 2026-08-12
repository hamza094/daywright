<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1\User;

use App\DataTransferObjects\User\PasswordUpdateData;
use Dedoc\Scramble\Attributes\SchemaName;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Password;

#[SchemaName('PasswordUpdateRequestData')]
class PasswordUpdateRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    public function toDto(): PasswordUpdateData
    {
        /** @var array<string, mixed> $validated */
        $validated = $this->validated();

        return PasswordUpdateData::fromValidated($validated);
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
             * Current password required for verification.
             *
             * @example CurrentPassword123!
             */
            'current_password' => ['required', 'current_password'],
            /**
             * New password. Submit a matching `password_confirmation` field alongside this value.
             *
             * @example NewPassword456!
             */
            'password' => ['required', Password::default(), 'confirmed'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'current_password.current_password' => 'The given password does not match your current password.',
            'password.mixed' => 'The password must include both uppercase and lowercase letters.',
            'password.letters' => 'The password must contain at least one letter.',
            'password.symbols' => 'The password must include at least one special character (symbol).',
            'password.numbers' => 'The password must contain at least one number.',
        ];
    }
}
