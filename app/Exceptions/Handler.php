<?php

declare(strict_types=1);

namespace App\Exceptions;

use App\Exceptions\Integrations\Zoom\ZoomException;
use App\Exceptions\Integrations\Zoom\ZoomUserErrorException;
use App\Exceptions\Paddle\SubscriptionException;
use App\Exceptions\Subscription\PlanLimitExceededException;
use App\Exceptions\Subscription\SubscriptionRequiredException;
use App\Exceptions\Traits\HandlesApiExceptions;
use App\Services\Zoom\ZoomLogContext;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Exceptions\Handler as ExceptionHandler;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;
use Override;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Symfony\Component\HttpKernel\Exception\MethodNotAllowedHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Throwable;

class Handler extends ExceptionHandler
{
    use HandlesApiExceptions;

    private const string API_PREFIX = 'api/*';

    private const string EXCEPTION_METRICS_CHANNEL = 'exception_metrics';

    private const string EXCEPTION_METRIC_EVENT = 'api_exception_metric';

    private const string ZOOM_CHANNEL = 'zoom';

    private const string ZOOM_REQUEST_FAILED_EVENT = 'zoom_request_failed';

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
        if ($e instanceof ZoomException) {
            $this->recordZoomException($e);
        }

        if ($this->shouldRecordExceptionMetric($e)) {
            if ($e instanceof ApiException) {
                $this->recordExceptionMetric($e);
            }

            return;
        }

        parent::report($e);
    }

    /**
     * Register the exception handling callbacks for the application.
     */
    #[Override]
    public function shouldReport(Throwable $e): bool
    {
        if ($e instanceof HttpException && $e->getStatusCode() < Response::HTTP_INTERNAL_SERVER_ERROR) {
            return false;
        }

        return parent::shouldReport($e);
    }

    #[Override]
    public function register(): void
    {
        $this->registerApiExceptionHandlers();
    }

    /**
     * Ensure the $request is treated as mixed for static analysis; the
     * actual runtime value may be an HTTP request or another context.
     *
     * @param  mixed  $request
     */
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
            'exception' => $e,
            'code' => $e->errorCode(),
            'status' => $e->status(),
            'message' => $e->publicMessage(),
        ];

        /** @var mixed $request */
        $request = request();

        if ($request instanceof Request) {
            $context['path'] = $request->path();
            $context['method'] = $request->method();
            $context['meta'] = $e->meta($request);
        }

        if (method_exists($e, 'context')) {
            $context['context'] = $e->context();
        }

        Log::channel(self::EXCEPTION_METRICS_CHANNEL)->info(self::EXCEPTION_METRIC_EVENT, $context);
    }

    private function recordZoomException(ZoomException $e): void
    {
        /** @var mixed $request */
        $request = request();

        $context = $request instanceof Request
            ? ZoomLogContext::forRequest($request, $e)
            : ['provider' => 'zoom'];

        $context['exception'] = $e;
        $context['code'] = $e->errorCode();
        $context['status'] = $e->status();
        $context['message'] = $e->publicMessage();

        Log::channel(self::ZOOM_CHANNEL)->{$this->zoomExceptionLogLevel($e)}(
            self::ZOOM_REQUEST_FAILED_EVENT,
            $context,
        );
    }

    private function zoomExceptionLogLevel(ZoomException $e): string
    {
        if ($e->getCode() === Response::HTTP_TOO_MANY_REQUESTS) {
            return 'warning';
        }

        return $e->status() >= Response::HTTP_INTERNAL_SERVER_ERROR
            ? 'error'
            : 'warning';
    }
}
