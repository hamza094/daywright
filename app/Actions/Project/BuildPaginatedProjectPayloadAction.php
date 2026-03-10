<?php

declare(strict_types=1);

namespace App\Actions\Project;

use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Pagination\LengthAwarePaginator;

final class BuildPaginatedProjectPayloadAction
{
    /**
     * @param  LengthAwarePaginator<mixed>  $paginator
     * @param  class-string<JsonResource>  $resourceClass
     * @return array{
     *     data: array<int, mixed>,
     *     links: array{first: string, last: string, prev: string|null, next: string|null},
     *     meta: array{current_page:int, from:int|null, last_page:int, links: array<int, mixed>, path:string, per_page:int, to:int|null, total:int}
     * }
     */
    public function handle(LengthAwarePaginator $paginator, string $resourceClass): array
    {
        return [
            'data' => $resourceClass::collection(collect($paginator->items()))->resolve(),
            'links' => [
                'first' => $paginator->url(1),
                'last' => $paginator->url($paginator->lastPage()),
                'prev' => $paginator->previousPageUrl(),
                'next' => $paginator->nextPageUrl(),
            ],
            'meta' => [
                'current_page' => $paginator->currentPage(),
                'from' => $paginator->firstItem(),
                'last_page' => $paginator->lastPage(),
                'links' => $paginator->linkCollection()->toArray(),
                'path' => $paginator->path(),
                'per_page' => $paginator->perPage(),
                'to' => $paginator->lastItem(),
                'total' => $paginator->total(),
            ],
        ];
    }
}
