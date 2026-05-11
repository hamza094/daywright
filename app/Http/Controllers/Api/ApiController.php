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
use Illuminate\Http\Resources\Json\JsonResource;
use JsonSerializable;
use Symfony\Component\HttpFoundation\Response;

class ApiController extends Controller
{
    use AuthorizesRequests, DispatchesJobs, ValidatesRequests;

    protected function respondWithData(array|Arrayable|JsonSerializable $data, int $status = Response::HTTP_OK): JsonResponse
    {
        return response()->json([
            'data' => $this->normalizeResponseData($data),
        ], $status);
    }

    protected function respondCreated(array|Arrayable|JsonSerializable $data): JsonResponse
    {
        return $this->respondWithData($data, Response::HTTP_CREATED);
    }

    protected function respondUpdated(array|Arrayable|JsonSerializable $data): JsonResponse
    {
        return $this->respondWithData($data);
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
}
