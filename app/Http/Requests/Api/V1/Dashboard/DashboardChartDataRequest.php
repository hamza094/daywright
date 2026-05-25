<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1\Dashboard;

use App\Http\Requests\Api\V1\ApiQueryRequest;
use Override;

class DashboardChartDataRequest extends ApiQueryRequest
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
            'year' => ['nullable', 'integer', 'digits:4', 'required_with:month'],
            /**
             * Optional project creation month filter.
             *
             * @example 5
             */
            'month' => ['nullable', 'integer', 'between:1,12'],
        ];
    }

    public function year(): ?int
    {
        $year = $this->validated('year');

        return is_numeric($year) ? (int) $year : null;
    }

    public function month(): ?int
    {
        $month = $this->validated('month');

        return is_numeric($month) ? (int) $month : null;
    }

    #[Override]
    public function messages(): array
    {
        return [
            'year.required_with' => 'Year is required when month is provided.',
            'month.between' => 'Month must be between 1 and 12.',
        ];
    }

    /**
     * @return array<int, string>
     */
    #[Override]
    protected function supportedTopLevelQueryParameters(): array
    {
        return ['year', 'month'];
    }
}
