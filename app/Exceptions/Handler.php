<?php

declare(strict_types=1);

namespace App\Exceptions;

use App\Exceptions\Integrations\Zoom\ZoomUserErrorException;
use App\Exceptions\Paddle\SubscriptionException;
use App\Exceptions\Subscription\PlanLimitExceededException;
use App\Exceptions\Subscription\SubscriptionRequiredException;
use App\Exceptions\Support\ApiErrorFormatter;
use Aws\S3\Exception\S3Exception;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Exceptions\Handler as ExceptionHandler;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;
use Override;
use Saloon\RateLimitPlugin\Exceptions\RateLimitReachedException;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Symfony\Component\HttpKernel\Exception\MethodNotAllowedHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Throwable;

class Handler extends ExceptionHandler
{
    private const string API_PREFIX = 'api/*';

    private const string EXCEPTION_METRICS_CHANNEL = 'exception_metrics';

    private const string EXCEPTION_METRIC_EVENT = 'api_exception_metric';

    /**
     * A list of the exception types that are not reported.
     *
     * @var array
     */
    protected $dontReport = [
        ValidationException::class,
        AuthenticationException::class,
        AuthorizationException::class,
        ModelNotFoundException::class,
        NotFoundHttpException::class,
        MethodNotAllowedHttpException::class,
    ];

    /**
     * A list of the inputs that are never flashed for validation exceptions.
     *
     * @var array
     */
    protected $dontFlash = [
        'password',
        'password_confirmation',
    ];

    #[Override]
    public function report(Throwable $e): void
    {
        if ($this->shouldRecordExceptionMetric($e)) {
            $this->recordExceptionMetric($e);

            return;
        }

        parent::report($e);
    }

    /**
     * Register the exception handling callbacks for the application.
     */
    #[Override]
    public function register(): void
    {
        $this->renderable(function (ApiException $e, Request $request) {
            return ApiErrorFormatter::response(
                $e->publicMessage(),
                $e->status(),
                $e->errorCode(),
                $e->errors(),
                $e->meta($request),
            );
        });

        $this->renderable(function (NotFoundHttpException $e, $request) {
            return ApiErrorFormatter::response(
                ApiErrorFormatter::publicMessage($e->getMessage(), 'Resource not found.'),
                Response::HTTP_NOT_FOUND,
                'not_found',
            );
        });

        $this->renderable(function (AuthenticationException $e, $request) {
            return ApiErrorFormatter::response(
                'Authentication is required.',
                Response::HTTP_UNAUTHORIZED,
                'unauthenticated',
            );
        });

        $this->renderable(function (AuthorizationException $e, $request) {
            return ApiErrorFormatter::response(
                ApiErrorFormatter::publicMessage($e->getMessage(), 'You are not authorized to perform this action.'),
                Response::HTTP_FORBIDDEN,
                'forbidden',
            );
        });

        $this->renderable(function (MethodNotAllowedHttpException $e, $request) {
            return ApiErrorFormatter::response(
                'Method not allowed.',
                Response::HTTP_METHOD_NOT_ALLOWED,
                'method_not_allowed',
            );
        });

        $this->renderable(function (HttpException $e, $request) {
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

        $this->renderable(function (ValidationException $e, $request) {
            return ApiErrorFormatter::response(
                'Validation failed.',
                Response::HTTP_UNPROCESSABLE_ENTITY,
                'validation_error',
                $e->errors(),
            );
        });

        $this->renderable(function (RateLimitReachedException $e, $request) {
            return ApiErrorFormatter::response(
                'Too many requests. Please try again later.',
                Response::HTTP_TOO_MANY_REQUESTS,
                'rate_limited',
                meta: [
                    'retry_after_seconds' => $e->getLimit()->getRemainingSeconds(),
                ],
            );
        });

        $this->renderable(function (S3Exception $e, $request) {
            return ApiErrorFormatter::response(
                'Storage request could not be completed.',
                Response::HTTP_INTERNAL_SERVER_ERROR,
                'storage_error',
                meta: [
                    'provider' => 's3',
                ],
            );
        });

        $this->renderable(function (Throwable $e, $request) {
            return ApiErrorFormatter::response(
                'An unexpected server error occurred.',
                Response::HTTP_INTERNAL_SERVER_ERROR,
                'internal_server_error',
            );
        });

    }

    #[Override]
    protected function renderViaCallbacks($request, Throwable $e)
    {
        if (! $request instanceof Request || ! $this->isApiRequest($request)) {
            return null;
        }

        return parent::renderViaCallbacks($request, $e);
    }

    private function isApiRequest(Request $request): bool
    {
        return $request->is(self::API_PREFIX);
    }

    private function shouldRecordExceptionMetric(Throwable $e): bool
    {
        return $e instanceof ArchivedResourceException
            || $e instanceof SubscriptionRequiredException
            || $e instanceof PlanLimitExceededException
            || $e instanceof SubscriptionException
            || $e instanceof ZoomUserErrorException;
    }

    private function recordExceptionMetric(ApiException $e): void
    {
        $context = [
            'exception' => $e::class,
            'code' => $e->errorCode(),
            'status' => $e->status(),
            'message' => $e->publicMessage(),
        ];

        $request = request();

        if ($request instanceof Request) {
            $context['path'] = $request->path();
            $context['method'] = $request->method();
            $context['meta'] = $e->meta($request);
        }

        Log::channel(self::EXCEPTION_METRICS_CHANNEL)->info(self::EXCEPTION_METRIC_EVENT, $context);
    }
}
