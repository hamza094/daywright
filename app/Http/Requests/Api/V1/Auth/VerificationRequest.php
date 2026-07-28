<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1\Auth;

use App\DataTransferObjects\Auth\VerificationData;
use Dedoc\Scramble\Attributes\SchemaName;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\URL;

#[SchemaName('VerificationRequestData')]
class VerificationRequest extends FormRequest
{
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
        return [];
    }

    public function toDto(): VerificationData
    {
        return new VerificationData(
            hash: (string) $this->query('hash', ''),
            hasValidSignature: URL::hasValidSignature($this),
        );
    }
}
