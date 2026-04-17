<?php

declare(strict_types=1);

namespace App\Actions;

use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Pagination\CursorPaginator;

final class BuildCursorPaginatedPayloadAction
{
    /**
     * @param  CursorPaginator<mixed>  $paginator
     * @param  class-string<JsonResource>  $resourceClass
     * @return array{
     *     data: array<int, mixed>,
     *     meta: array{path: string, per_page: int, next_cursor: string|null, prev_cursor: string|null, has_more: bool}
     * }
     */
    public function handle(CursorPaginator $paginator, string $resourceClass): array
    {
        return [
            'data' => $resourceClass::collection(collect($paginator->items()))->resolve(),
            'meta' => [
                'path' => $paginator->path(),
                'per_page' => $paginator->perPage(),
                'next_cursor' => $paginator->nextCursor()?->encode(),
                'prev_cursor' => $paginator->previousCursor()?->encode(),
                'has_more' => $paginator->hasMorePages(),
            ],
        ];
    }
}
