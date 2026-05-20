<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1\User;

use Dedoc\Scramble\Attributes\SchemaName;
use Illuminate\Foundation\Http\FormRequest;
use Override;

#[SchemaName('UserAvatarUploadRequestData')]
class UserAvatarStoreRequest extends FormRequest
{
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
             * Avatar image file.
             * Accepted types: jpeg, jpg, png. Maximum size: 700 KB.
             */
            'avatar' => ['required', 'image', 'max:700', 'mimes:jpeg,png,jpg'],
        ];
    }

    /**
     * @return array<string, string>
     */
    #[Override]
    public function messages(): array
    {
        return [
            'avatar.required' => 'Please upload an avatar image.',
            'avatar.image' => 'The avatar must be an image.',
            'avatar.max' => 'The avatar image may not be greater than 700 kilobytes.',
            'avatar.mimes' => 'The avatar image must be a file of type: jpeg, png, jpg.',
        ];
    }
}
