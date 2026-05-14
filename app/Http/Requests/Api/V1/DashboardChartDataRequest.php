<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1;

use Illuminate\Foundation\Http\FormRequest;

class DashboardChartDataRequest extends FormRequest
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
             * Optional project creation year filter.
             *
             * @example 2026
             */
            'year' => ['sometimes', 'integer'],
            /**
             * Optional project creation month filter.
             *
             * @example 5
             */
            'month' => ['sometimes', 'integer'],
        ];
    }
}
