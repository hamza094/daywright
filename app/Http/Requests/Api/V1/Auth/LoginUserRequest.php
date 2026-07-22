<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1\Auth;

use App\DataTransferObjects\Auth\LoginCredentialsData;
use App\Models\User;
use Dedoc\Scramble\Attributes\SchemaName;
use Illuminate\Contracts\Validation\Validator as ValidatorContract;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Hash;

#[SchemaName('LoginRequestData')]
class LoginUserRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    public function toDto(): LoginCredentialsData
    {
        return LoginCredentialsData::fromArray($this->validated());
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            /**
             * Email address used to identify the account.
             *
             * @example berry@example.com
             */
            'email' => 'required|email',
            /**
             * Plain-text account password.
             *
             * @example Berry@04
             */
            'password' => 'required|string',
        ];
    }

    /**
     * After base validation, verify credentials belong to a real user.
     */
    public function withValidator(ValidatorContract $validator): void
    {
        $validator->after(function (ValidatorContract $validator): void {
            if ($validator->errors()->isNotEmpty()) {
                return;
            }

            $email = $this->input('email');
            $password = $this->input('password');

            $user = User::where('email', $email)->first();

            if (! $user || ! Hash::check($password, $user->password)) {
                $validator->errors()->add('email', 'The provided credentials are incorrect.');
            }
        });
    }
}
