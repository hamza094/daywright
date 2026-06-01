<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1\Auth;

use Dedoc\Scramble\Attributes\SchemaName;
use Illuminate\Foundation\Http\FormRequest;

#[SchemaName('ForgotPasswordRequestData')]
class ForgotPasswordRequest extends FormRequest
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
             * Email address that should receive the password reset link.
             *
             * @example berry@example.com
             */
            'email' => 'required|email',
        ];
    }
}
