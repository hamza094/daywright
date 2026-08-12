<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1\Auth;

use App\DataTransferObjects\Auth\RecoveryCodesData;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Form Request for Regenerating Recovery Codes
 *
 * Validates that the user provides their current password before generating new recovery codes.
 */
final class RecoveryCodesRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return auth()->check();
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'current_password' => ['required', 'string', 'current_password'],
        ];
    }

    public function toDto(): RecoveryCodesData
    {
        return RecoveryCodesData::fromValidated($this->validated());
    }
}
