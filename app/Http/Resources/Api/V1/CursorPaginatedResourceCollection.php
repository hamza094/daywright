<?php

declare(strict_types=1);

namespace App\Http\Resources\Api\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Http\Resources\Json\ResourceCollection;
use Illuminate\Pagination\CursorPaginator;

final class CursorPaginatedResourceCollection extends ResourceCollection
{
    /**
     * @var class-string<JsonResource>
     */
    public $collects;

    /**
     * @var array<string, mixed>
     */
    private array $additionalMeta;

    /**
     * @param  CursorPaginator<mixed>  $resource
     * @param  class-string<JsonResource>  $collects
     * @param  array<string, mixed>  $additionalMeta
     */
    public function __construct(CursorPaginator $resource, string $collects, array $additionalMeta = [])
    {
        $this->collects = $collects;
        $this->additionalMeta = $additionalMeta;

        parent::__construct($resource);
    }

    /**
     * @param  array<string, mixed>  $paginated
     * @param  array{links: array<string, mixed>, meta: array<string, mixed>}  $default
     * @return array{meta: array<string, mixed>}
     */
    public function paginationInformation(Request $request, array $paginated, array $default): array
    {
        return [
            'meta' => array_merge(
                $default['meta'],
                ['has_more' => $this->resource->hasMorePages()],
                $this->additionalMeta,
            ),
        ];
    }
}
