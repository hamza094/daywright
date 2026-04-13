<?php

declare(strict_types=1);

namespace App\Exceptions;

use App\Exceptions\Integrations\Zoom\NotFoundException;
use App\Exceptions\Integrations\Zoom\UnauthorizedException;
use App\Exceptions\Integrations\Zoom\ZoomException;
use App\Exceptions\Subscription\PlanLimitExceededException;
use App\Models\Project;
use App\Models\Task;
use Aws\S3\Exception\S3Exception;
use Exception;
use Illuminate\Foundation\Exceptions\Handler as ExceptionHandler;
use Illuminate\Http\Request;
use Laravel\Paddle\Exceptions\PaddleException as LaravelPaddleException;
use Override;
use Saloon\RateLimitPlugin\Exceptions\RateLimitReachedException;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Symfony\Component\HttpKernel\Exception\MethodNotAllowedHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Throwable;

class Handler extends ExceptionHandler
{
    private const string API_PREFIX = 'api/*';

    /**
     * A list of the exception types that are not reported.
     *
     * @var array
     */
    protected $dontReport = [

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

    /**
     * Report or log an exception.
     *
     *
     * @throws Exception
     */
    #[Override]
    public function report(Throwable $exception): void
    {
        parent::report($exception);
    }

    /**
     * Register the exception handling callbacks for the application.
     */
    #[Override]
    public function register(): void
    {
        $this->reportable(function (Throwable $e): void {});

        $this->renderable(function (NotFoundHttpException $e, $request) {
            if ($request->is(self::API_PREFIX)) {
                if ($this->trashedProjectRequested($request)) {
                    return response()->json([
                        'message' => 'Sorry, project is not active. Restore it to perform this activity.',
                    ], 403);
                }

                if ($this->trashedTaskRequested($request)) {
                    return response()->json([
                        'message' => 'Sorry, task is not active. Restore it to perform this activity.',
                    ], 403);
                }

                return response()->json([
                    'message' => 'Sorry Record not found.',
                ], 404);
            }
        });

        $this->renderable(function (PlanLimitExceededException $e, $request) {
            if ($request->is(self::API_PREFIX)) {
                $authenticatedUser = $request->user();
                $canUpgrade = $e->limitScope() === PlanLimitExceededException::SCOPE_ACCOUNT
                    || (int) ($authenticatedUser?->getKey() ?? 0) === $e->limitOwnerId();

                return response()->json([
                    'message' => $e->getMessage(),
                    'error_type' => 'plan_limit_exceeded',
                    'reason' => $e->reason(),
                    'limit_type' => $e->limitType(),
                    'limit_label' => $e->limitLabel(),
                    'current_usage' => $e->currentUsage(),
                    'max_allowed' => $e->maxAllowed(),
                    'limit_scope' => $e->limitScope(),
                    'can_upgrade' => $canUpgrade,
                    'upgrade_required' => true,
                ], 403);
            }
        });

        $this->renderable(function (HttpException $e, $request) {
            if ($request->is(self::API_PREFIX)) {
                return response()->json([
                    'message' => $e->getMessage(),
                ], $e->getStatusCode());
            }
        });

        $this->renderable(function (MethodNotAllowedHttpException $e, $request) {
            if ($request->is(self::API_PREFIX)) {
                return response()->json([
                    'message' => 'The HTTP method used for the request is not allowed.',
                ], 405);
            }
        });

        $this->renderable(function (\Illuminate\Validation\ValidationException $e, $request) {
            if ($request->is(self::API_PREFIX)) {
                return response()->json([
                    'message' => 'Validation Error',
                    'errors' => $e->errors(),
                ], 422);
            }
        });

        $this->renderable(function (LaravelPaddleException $e, $request) {
            if ($request->is(self::API_PREFIX)) {
                return response()->json([
                    'message' => 'A payment error occurred: '.$e->getMessage(),
                ], 422);
            }
        });

        $this->renderable(function (ZoomException $e, $request) {
            if ($request->is(self::API_PREFIX)) {
                return response()->json([
                    'error' => $e->getMessage() ?: 'Zoom error',
                ], 400);
            }
        });

        $this->renderable(function (NotFoundException $e, $request) {
            if ($request->is(self::API_PREFIX)) {
                return response()->json([
                    'error' => $e->getMessage() ?: 'Resource not found',
                ], 404);
            }
        });

        $this->renderable(function (UnauthorizedException $e, $request) {
            if ($request->is(self::API_PREFIX)) {
                return response()->json([
                    'error' => $e->getMessage() ?: 'Unauthorized',
                ], 403);
            }
        });

        $this->renderable(function (RateLimitReachedException $e, $request) {
            if ($request->is(self::API_PREFIX)) {
                return response()->json([
                    'error' => 'Rate limit exceeded. Please try again in '.$e->getLimit()->getRemainingSeconds().' seconds.',
                ], 429);
            }
        });

        $this->renderable(function (S3Exception $e, $request) {
            if ($request->is(self::API_PREFIX)) {
                return response()->json([
                    'message' => 'S3 Error: '.$e->getMessage(),
                ], 500);
            }
        });

    }

    private function trashedProjectRequested(Request $request): bool
    {
        $projectRouteParameter = $request->route('project');

        if ($projectRouteParameter instanceof Project) {
            return $projectRouteParameter->trashed();
        }

        if (! is_string($projectRouteParameter) || $projectRouteParameter === '') {
            return false;
        }

        $routeKeyName = (new Project)->getRouteKeyName();

        return Project::onlyTrashed()
            ->where($routeKeyName, $projectRouteParameter)
            ->exists();
    }

    private function trashedTaskRequested(Request $request): bool
    {
        $taskRouteParameter = $request->route('task');

        if ($taskRouteParameter instanceof Task) {
            return $taskRouteParameter->trashed();
        }

        if (! is_string($taskRouteParameter) || $taskRouteParameter === '') {
            return false;
        }

        $routeKeyName = (new Task)->getRouteKeyName();

        return Task::onlyTrashed()
            ->where($routeKeyName, $taskRouteParameter)
            ->exists();
    }
}
