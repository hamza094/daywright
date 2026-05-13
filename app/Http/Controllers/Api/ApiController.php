<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Contracts\Support\Arrayable;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Foundation\Bus\DispatchesJobs;
use Illuminate\Foundation\Validation\ValidatesRequests;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Pagination\LengthAwarePaginator;
use JsonSerializable;
use Symfony\Component\HttpFoundation\Response;

class ApiController extends Controller
{
    use AuthorizesRequests, DispatchesJobs, ValidatesRequests;

    protected function respondWithData(
        array|Arrayable|JsonSerializable $data,
        int $status = Response::HTTP_OK,
        array $meta = [],
        array $links = [],
    ): JsonResponse {
        return $this->respondWithPayload($data, $status, $meta, $links);
    }

    protected function respondCreated(array|Arrayable|JsonSerializable $data): JsonResponse
    {
        return $this->respondWithData($data, Response::HTTP_CREATED);
    }

    protected function respondUpdated(array|Arrayable|JsonSerializable $data): JsonResponse
    {
        return $this->respondWithData($data);
    }

    protected function respondWithPaginatedData(
        array|Arrayable|JsonSerializable $data,
        LengthAwarePaginator $paginator,
        int $status = Response::HTTP_OK,
        array $meta = [],
    ): JsonResponse {
        // If the caller already built a resource collection from a paginator,
        // prefer Laravel's native response which handles `data/meta/links`.
        if ($data instanceof AnonymousResourceCollection && $data->resource instanceof LengthAwarePaginator) {
            if ($meta !== []) {
                $data->additional(['meta' => $meta]);
            }

            return $data->response()->setStatusCode($status);
        }

        return $this->respondWithPayload(
            $data,
            $status,
            array_merge([
                'current_page' => $paginator->currentPage(),
                'from' => $paginator->firstItem(),
                'last_page' => $paginator->lastPage(),
                'links' => $paginator->linkCollection()->toArray(),
                'path' => $paginator->path(),
                'per_page' => $paginator->perPage(),
                'to' => $paginator->lastItem(),
                'total' => $paginator->total(),
            ], $meta),
            [
                'first' => $paginator->url(1),
                'last' => $paginator->url($paginator->lastPage()),
                'prev' => $paginator->previousPageUrl(),
                'next' => $paginator->nextPageUrl(),
            ],
        );
    }

    protected function respondWithMessage(string $message, int $status = Response::HTTP_OK): JsonResponse
    {
        return response()->json([
            'message' => $message,
        ], $status);
    }

    protected function authenticatedUser(): User
    {
        $user = auth()->user();

        if (! $user instanceof User) {
            throw new AuthenticationException('Unauthenticated.');
        }

        return $user;
    }

    private function normalizeResponseData(array|Arrayable|JsonSerializable $data): mixed
    {
        if ($data instanceof JsonResource) {
            return $data->resolve();
        }

        if ($data instanceof Arrayable) {
            return $data->toArray();
        }

        if ($data instanceof JsonSerializable) {
            return $data->jsonSerialize();
        }

        return $data;
    }

    private function respondWithPayload(
        array|Arrayable|JsonSerializable $data,
        int $status,
        array $meta = [],
        array $links = [],
    ): JsonResponse {
        $payload = [
            'data' => $this->normalizeResponseData($data),
        ];

        if ($meta !== []) {
            $payload['meta'] = $meta;
        }

        if ($links !== []) {
            $payload['links'] = $links;
        }

        return response()->json($payload, $status);
    }
}
