<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1\User;

use App\Http\Requests\Api\V1\ApiQueryRequest;
use Carbon\Carbon;
use Illuminate\Validation\Validator;
use Override;

class UserActivitiesRequest extends ApiQueryRequest
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
     */
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            /**
             * Inclusive start date for the dashboard activity window.
             *
             * @example 2025-08-01
             */
            'start_date' => 'required|date_format:Y-m-d',
            /**
             * Inclusive end date for the dashboard activity window.
             * Must be the same as or after `start_date`.
             *
             * @example 2025-08-31
             */
            'end_date' => 'required|date_format:Y-m-d|after_or_equal:start_date',
        ];
    }

    /**
     * Get the validated and transformed date range.
     *
     * @return array{start_date: Carbon, end_date: Carbon}
     */
    public function getDateRange(): array
    {
        $validated = $this->validated();

        return [
            'start_date' => Carbon::parse($validated['start_date'])->startOfDay(),
            'end_date' => Carbon::parse($validated['end_date'])->endOfDay(),
        ];
    }

    #[Override]
    protected function withValidator(Validator $validator): void
    {
        parent::withValidator($validator);

        $validator->after(function ($validator): void {
            if ($validator->errors()->has('start_date') || $validator->errors()->has('end_date')) {
                return;
            }

            $start = $this->input('start_date');
            $end = $this->input('end_date');

            if ($start && $end && Carbon::parse($start)->diffInDays(Carbon::parse($end)) > 365) {
                $validator->errors()->add('end_date', 'The date range may not exceed 365 days.');
            }
        });
    }

    /**
     * @return array<int, string>
     */
    #[Override]
    protected function supportedTopLevelQueryParameters(): array
    {
        return ['start_date', 'end_date'];
    }
}
