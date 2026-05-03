<?php

declare(strict_types=1);

namespace App\Exceptions;

use App\Exceptions\Integrations\Zoom\NotFoundException;
use App\Exceptions\Integrations\Zoom\UnauthorizedException;
use App\Exceptions\Integrations\Zoom\ZoomException;
use App\Exceptions\Subscription\PlanLimitExceededException;
use App\Exceptions\Subscription\SubscriptionRequiredException;
use App\Models\Project;
use App\Models\Task;
use Aws\S3\Exception\S3Exception;
use Exception;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Foundation\Exceptions\Handler as ExceptionHandler;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Laravel\Paddle\Exceptions\PaddleException as LaravelPaddleException;
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
            if ($this->isApiRequest($request)) {
                if ($this->trashedProjectRequested($request)) {
                    return $this->apiErrorResponse(
                        'Sorry, project is not active. Restore it to perform this activity.',
                        Response::HTTP_FORBIDDEN,
                    );
                }

                if ($this->trashedTaskRequested($request)) {
                    return $this->apiErrorResponse(
                        'Sorry, task is not active. Restore it to perform this activity.',
                        Response::HTTP_FORBIDDEN,
                    );
                }

                if ($e->getMessage() !== '' && $e->getMessage() !== 'Not Found') {
                    return $this->apiErrorResponse($e->getMessage(), Response::HTTP_NOT_FOUND);
                }

                return $this->apiErrorResponse('Sorry Record not found.', Response::HTTP_NOT_FOUND);
            }
        });

        $this->renderable(function (PlanLimitExceededException $e, $request) {
            if ($this->isApiRequest($request)) {
                $authenticatedUser = $request->user();
                $canUpgrade = $e->limitScope() === PlanLimitExceededException::SCOPE_ACCOUNT
                    || (int) ($authenticatedUser?->getKey() ?? 0) === $e->limitOwnerId();

                return $this->apiErrorResponse(
                    $e->getMessage(),
                    Response::HTTP_FORBIDDEN,
                    additionalData: [
                        'error_type' => 'plan_limit_exceeded',
                        'reason' => $e->reason(),
                        'limit_type' => $e->limitType(),
                        'limit_label' => $e->limitLabel(),
                        'current_usage' => $e->currentUsage(),
                        'max_allowed' => $e->maxAllowed(),
                        'limit_scope' => $e->limitScope(),
                        'can_upgrade' => $canUpgrade,
                        'upgrade_required' => true,
                    ],
                );
            }
        });

        $this->renderable(function (SubscriptionRequiredException $e, $request) {
            if ($this->isApiRequest($request)) {
                return $this->apiErrorResponse(
                    $e->getMessage(),
                    Response::HTTP_FORBIDDEN,
                    additionalData: [
                        'error_type' => $e->errorType(),
                        'upgrade_required' => $e->upgradeRequired(),
                    ],
                );
            }
        });

        $this->renderable(function (AuthenticationException $e, $request) {
            if ($this->isApiRequest($request)) {
                return $this->apiErrorResponse(
                    $e->getMessage() !== '' ? $e->getMessage() : 'Unauthenticated.',
                    Response::HTTP_UNAUTHORIZED,
                );
            }
        });

        $this->renderable(function (AuthorizationException $e, $request) {
            if ($this->isApiRequest($request)) {
                return $this->apiErrorResponse(
                    $e->getMessage() !== '' ? $e->getMessage() : 'This action is unauthorized.',
                    Response::HTTP_FORBIDDEN,
                );
            }
        });

        $this->renderable(function (HttpException $e, $request) {
            if ($this->isApiRequest($request)) {
                $message = $e->getMessage() !== ''
                    ? $e->getMessage()
                    : $this->defaultApiMessageForStatus($e->getStatusCode());

                return $this->apiErrorResponse($message, $e->getStatusCode());
            }
        });

        $this->renderable(function (MethodNotAllowedHttpException $e, $request) {
            if ($this->isApiRequest($request)) {
                return $this->apiErrorResponse(
                    'The HTTP method used for the request is not allowed.',
                    Response::HTTP_METHOD_NOT_ALLOWED,
                );
            }
        });

        $this->renderable(function (ValidationException $e, $request) {
            if ($this->isApiRequest($request)) {
                return $this->apiErrorResponse(
                    'Validation Error',
                    Response::HTTP_UNPROCESSABLE_ENTITY,
                    $e->errors(),
                );
            }
        });

        $this->renderable(function (LaravelPaddleException $e, $request) {
            if ($this->isApiRequest($request)) {
                return $this->apiErrorResponse(
                    'A payment error occurred: '.$e->getMessage(),
                    Response::HTTP_UNPROCESSABLE_ENTITY,
                );
            }
        });

        $this->renderable(function (ZoomException $e, $request) {
            if ($this->isApiRequest($request)) {
                return $this->apiErrorResponse(
                    $e->getMessage() !== '' ? $e->getMessage() : 'Zoom error',
                    Response::HTTP_BAD_REQUEST,
                );
            }
        });

        $this->renderable(function (NotFoundException $e, $request) {
            if ($this->isApiRequest($request)) {
                return $this->apiErrorResponse(
                    $e->getMessage() !== '' ? $e->getMessage() : 'Resource not found',
                    Response::HTTP_NOT_FOUND,
                );
            }
        });

        $this->renderable(function (UnauthorizedException $e, $request) {
            if ($this->isApiRequest($request)) {
                return $this->apiErrorResponse(
                    $e->getMessage() !== '' ? $e->getMessage() : 'Unauthorized',
                    Response::HTTP_FORBIDDEN,
                );
            }
        });

        $this->renderable(function (RateLimitReachedException $e, $request) {
            if ($this->isApiRequest($request)) {
                return $this->apiErrorResponse(
                    'Rate limit exceeded. Please try again in '.$e->getLimit()->getRemainingSeconds().' seconds.',
                    Response::HTTP_TOO_MANY_REQUESTS,
                );
            }
        });

        $this->renderable(function (S3Exception $e, $request) {
            if ($this->isApiRequest($request)) {
                return $this->apiErrorResponse(
                    'S3 Error: '.$e->getMessage(),
                    Response::HTTP_INTERNAL_SERVER_ERROR,
                );
            }
        });

        $this->renderable(function (Throwable $e, $request) {
            if ($this->isApiRequest($request)) {
                return $this->apiErrorResponse(
                    'An unexpected error occurred.',
                    Response::HTTP_INTERNAL_SERVER_ERROR,
                );
            }
        });

    }

    /**
     * @param  array<string, mixed>  $additionalData
     * @param  array<string, array<int, string>>  $errors
     */
    private function apiErrorResponse(
        string $message,
        int $status,
        array $errors = [],
        array $additionalData = [],
    ): JsonResponse {
        $payload = ['message' => $message];

        if ($errors !== []) {
            $payload['errors'] = $errors;
        }

        return response()->json(array_merge($payload, $additionalData), $status);
    }

    private function defaultApiMessageForStatus(int $status): string
    {
        return Response::$statusTexts[$status] ?? 'An unexpected error occurred.';
    }

    private function isApiRequest(Request $request): bool
    {
        return $request->is(self::API_PREFIX);
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
