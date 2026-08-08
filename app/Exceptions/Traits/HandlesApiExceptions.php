<?php

declare(strict_types=1);

namespace App\Exceptions\Traits;

use App\Exceptions\ApiException;
use App\Exceptions\InvalidStateTransitionException;
use App\Exceptions\MaxTokensExceededException;
use App\Exceptions\Support\ApiErrorFormatter;
use Aws\S3\Exception\S3Exception;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Database\QueryException;
use Illuminate\Http\Exceptions\ThrottleRequestsException;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Saloon\RateLimitPlugin\Exceptions\RateLimitReachedException;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Symfony\Component\HttpKernel\Exception\MethodNotAllowedHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Throwable;

trait HandlesApiExceptions
{
    protected function registerApiExceptionHandlers(): void
    {
        $this->renderable(fn (InvalidStateTransitionException $e, Request $request): \Illuminate\Http\JsonResponse => ApiErrorFormatter::response(
            $e->publicMessage(),
            $e->status(),
            $e->errorCode(),
            $e->errors(),
            $e->meta($request),
        ));

        $this->renderable(fn (MaxTokensExceededException $e, Request $request): \Illuminate\Http\JsonResponse => ApiErrorFormatter::response(
            $e->publicMessage(),
            $e->status(),
            $e->errorCode(),
        ));

        $this->renderable(fn (ApiException $e, Request $request): \Illuminate\Http\JsonResponse => ApiErrorFormatter::response(
            $e->publicMessage(),
            $e->status(),
            $e->errorCode(),
            $e->errors(),
            $e->meta($request),
        ));

        $this->renderable(fn (ModelNotFoundException $e, $request): \Illuminate\Http\JsonResponse => ApiErrorFormatter::response(
            'Resource not found.',
            Response::HTTP_NOT_FOUND,
            'not_found',
        ));

        $this->renderable(fn (NotFoundHttpException $e, $request): \Illuminate\Http\JsonResponse => ApiErrorFormatter::response(
            'Resource not found.',
            Response::HTTP_NOT_FOUND,
            'not_found',
        ));

        $this->renderable(fn (AuthenticationException $e, $request): \Illuminate\Http\JsonResponse => ApiErrorFormatter::response(
            'Authentication is required.',
            Response::HTTP_UNAUTHORIZED,
            'unauthenticated',
        ));

        $this->renderable(fn (AuthorizationException $e, $request): \Illuminate\Http\JsonResponse => ApiErrorFormatter::response(
            ApiErrorFormatter::publicMessage($e->getMessage(), 'You are not authorized to perform this action.'),
            Response::HTTP_FORBIDDEN,
            'forbidden',
        ));

        $this->renderable(fn (MethodNotAllowedHttpException $e, $request): \Illuminate\Http\JsonResponse => ApiErrorFormatter::response(
            'Method not allowed.',
            Response::HTTP_METHOD_NOT_ALLOWED,
            'method_not_allowed',
        ));

        $this->renderable(function (ThrottleRequestsException $e, Request $request): \Illuminate\Http\JsonResponse {
            $headers = $e->getHeaders();
            $retryAfter = (int) ($headers['Retry-After'] ?? $headers['retry-after'] ?? 0);

            $response = ApiErrorFormatter::response(
                'Too many requests. Please try again later.',
                Response::HTTP_TOO_MANY_REQUESTS,
                'rate_limited',
                meta: array_filter([
                    'retry_after_seconds' => $retryAfter,
                ], fn (int $val): bool => $val > 0),
            );

            return $response->withHeaders($headers);
        });

        $this->renderable(function (HttpException $e): \Illuminate\Http\JsonResponse {
            $status = $e->getStatusCode();
            $defaultMessage = ApiErrorFormatter::defaultMessageForStatus($status);
            $message = $e->getMessage() !== ''
                ? ApiErrorFormatter::publicMessage($e->getMessage(), $defaultMessage)
                : $defaultMessage;

            if ($status >= Response::HTTP_INTERNAL_SERVER_ERROR) {
                $message = $defaultMessage;
            }

            return ApiErrorFormatter::response(
                $message,
                $status,
                ApiErrorFormatter::defaultCodeForStatus($status),
            );
        });

        $this->renderable(fn (ValidationException $e, $request): \Illuminate\Http\JsonResponse => ApiErrorFormatter::response(
            'Validation failed.',
            Response::HTTP_UNPROCESSABLE_ENTITY,
            'validation_error',
            $e->errors(),
        ));

        $this->renderable(fn (RateLimitReachedException $e, $request): \Illuminate\Http\JsonResponse => ApiErrorFormatter::response(
            'Too many requests. Please try again later.',
            Response::HTTP_TOO_MANY_REQUESTS,
            'rate_limited',
            meta: [
                'retry_after_seconds' => $e->getLimit()->getRemainingSeconds(),
            ],
        ));

        $this->renderable(fn (S3Exception $e, $request): \Illuminate\Http\JsonResponse => ApiErrorFormatter::response(
            'Storage request could not be completed.',
            Response::HTTP_INTERNAL_SERVER_ERROR,
            'storage_error',
            meta: [
                'provider' => 's3',
            ],
        ));

        $this->renderable(fn (QueryException $e, $request): \Illuminate\Http\JsonResponse => ApiErrorFormatter::response(
            'A database error occurred. Please try again.',
            Response::HTTP_INTERNAL_SERVER_ERROR,
            'database_error',
        ));

        $this->renderable(function (Throwable $e, $request): ?\Illuminate\Http\JsonResponse {
            if (! app()->isProduction()) {
                return null;
            }

            return ApiErrorFormatter::response(
                'An unexpected server error occurred.',
                Response::HTTP_INTERNAL_SERVER_ERROR,
                'internal_server_error',
            );
        });
    }
}
