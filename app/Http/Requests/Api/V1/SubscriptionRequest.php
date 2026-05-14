<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1;

use Dedoc\Scramble\Attributes\SchemaName;
use Illuminate\Foundation\Http\FormRequest;
use Override;

#[SchemaName('SubscriptionPlanRequestData')]
class SubscriptionRequest extends FormRequest
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
     * @return array<string, string>
     */
    public function rules(): array
    {
        return [
            /**
             * Billing plan identifier.
             *
             * @example monthly
             */
            'plan' => 'required|in:monthly,yearly',
        ];
    }

    #[Override]
    protected function prepareForValidation(): void
    {
        // Merge the route parameter "plan" into the request data if it's not already present.
        if (! $this->has('plan') && $this->route('plan')) {
            $this->merge(['plan' => $this->route('plan')]);
        }
    }
}
