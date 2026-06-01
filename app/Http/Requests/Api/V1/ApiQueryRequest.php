<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1;

use App\Http\Requests\Api\V1\Concerns\InteractsWithApiQueryFilters;
use App\Http\Requests\Api\V1\Concerns\InteractsWithApiQueryPagination;
use App\Http\Requests\Api\V1\Concerns\InteractsWithUnsupportedApiQueryParameters;
use Illuminate\Foundation\Http\FormRequest;
use Spatie\QueryBuilder\AllowedFilter;
use Spatie\QueryBuilder\AllowedInclude;
use Spatie\QueryBuilder\AllowedSort;

abstract class ApiQueryRequest extends FormRequest
{
    use InteractsWithApiQueryFilters;
    use InteractsWithApiQueryPagination;
    use InteractsWithUnsupportedApiQueryParameters;

    /**
     * @return array<int, AllowedFilter|string>
     */
    public static function allowedFilters(): array
    {
        return [];
    }

    /**
     * @return array<int, AllowedSort|string>
     */
    public static function allowedSorts(): array
    {
        return [];
    }

    /**
     * @return array<int, AllowedSort|string>
     */
    public static function defaultSorts(): array
    {
        return [];
    }

    /**
     * @return array<int, AllowedInclude|string>
     */
    public static function allowedIncludes(): array
    {
        return [];
    }
}
