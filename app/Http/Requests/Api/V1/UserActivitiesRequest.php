<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1;

use Carbon\Carbon;
use Closure;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class UserActivitiesRequest extends FormRequest
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
    public function rules(): array
    {
        return [
            'start_date' => 'required|date_format:Y-m-d',
            'end_date' => 'required|date_format:Y-m-d|after_or_equal:start_date',
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
    public function getDateRange(): array
    {
        $validated = $this->validated();

        return [
            'start_date' => Carbon::parse($validated['start_date'])->startOfDay(),
            'end_date' => Carbon::parse($validated['end_date'])->endOfDay(),
        ];
    }
}
