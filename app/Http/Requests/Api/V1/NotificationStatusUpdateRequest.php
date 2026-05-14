<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1;

use Dedoc\Scramble\Attributes\SchemaName;
use Illuminate\Foundation\Http\FormRequest;
use Override;

#[SchemaName('NotificationStatusUpdateRequestData')]
class NotificationStatusUpdateRequest extends FormRequest
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
             * New notification state.
             *
             * @example read
             */
            'status' => ['required', 'in:read,unread'],
        ];
    }

    /**
     * @return array<string, string>
     */
    #[Override]
    public function messages(): array
    {
        return [
            'status.required' => 'Please provide a notification status.',
            'status.in' => 'The notification status must be read or unread.',
        ];
    }
}
