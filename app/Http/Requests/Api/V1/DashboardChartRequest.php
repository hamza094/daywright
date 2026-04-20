<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1;

use Illuminate\Foundation\Http\FormRequest;

class DashboardChartRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, string>
     */
    public function rules(): array
    {
        return [
            'year' => 'nullable|integer|min:1',
            'month' => 'nullable|integer|between:1,12',
        ];
    }

    /**
     * @return array{year:?int, month:?int}
     */
    public function getChartFilters(): array
    {
        $validated = $this->validated();

        return [
            'year' => array_key_exists('year', $validated) ? $this->integer('year') : null,
            'month' => array_key_exists('month', $validated) ? $this->integer('month') : null,
        ];
    }
}
