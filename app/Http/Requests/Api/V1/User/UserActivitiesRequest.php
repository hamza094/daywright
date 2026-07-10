<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1\User;

use App\DataTransferObjects\Activity\DateRange;
use App\Http\Requests\Api\V1\ApiQueryRequest;
use Carbon\Carbon;
use Closure;
use Illuminate\Validation\Validator;
use Override;

class UserActivitiesRequest extends ApiQueryRequest
{
    private const int MAX_DATE_RANGE_DAYS = 31;

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

    /**
     * @return array<int, Closure(Validator): void>
     */
    public function after(): array
    {
        return [
            function (Validator $validator): void {
                if ($validator->errors()->has('start_date') || $validator->errors()->has('end_date')) {
                    return;
                }

                $startDate = Carbon::createFromFormat('Y-m-d', (string) $this->input('start_date'));
                $endDate = Carbon::createFromFormat('Y-m-d', (string) $this->input('end_date'));

                if ($endDate->greaterThan($startDate->copy()->addDays(self::MAX_DATE_RANGE_DAYS - 1))) {
                    $validator->errors()->add(
                        'end_date',
                        sprintf('The selected date range may not exceed %d days.', self::MAX_DATE_RANGE_DAYS)
                    );
                }
            },
        ];
    }

    /**
     * Get the validated and transformed date range.
     */
    public function getDateRange(): DateRange
    {
        return DateRange::fromArray($this->validated());
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
