<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1\User;

use App\Rules\Iso8601Timestamp;
use Closure;
use Dedoc\Scramble\Attributes\SchemaName;
use Illuminate\Foundation\Http\FormRequest;
use Override;

#[SchemaName('ApiTokenStoreRequestData')]
class UserTokenRequest extends FormRequest
{
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
             * Human-readable label for the token.
             *
             * @example My API Token
             */
            'name' => 'required|string|max:255',

            /**
             * Optional ISO 8601 expiration timestamp with timezone offset.
             * Must not be more than 180 days from now.
             *
             * @example 2025-12-31T23:59:59+00:00
             */
            'expires_at' => [
                'bail',
                'nullable',
                new Iso8601Timestamp,
                function (string $attribute, mixed $value, Closure $fail): void {
                    if ($value) {
                        $maxDate = now()->addDays(180);
                        if (\Carbon\Carbon::parse($value)->gt($maxDate)) {
                            $fail('The '.$attribute.' may not be more than 180 days from now.');
                        }
                    }
                },
            ],
        ];
    }

    #[Override]
    protected function prepareForValidation(): void
    {
        if (! $this->filled('expires_at')) {
            return;
        }

        $normalizedExpiresAt = Iso8601Timestamp::normalizeToUtc((string) $this->input('expires_at'));

        if ($normalizedExpiresAt === null) {
            return;
        }

        $this->merge([
            'expires_at' => $normalizedExpiresAt,
        ]);
    }
}
