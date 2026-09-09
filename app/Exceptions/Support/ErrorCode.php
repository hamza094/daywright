<?php

declare(strict_types=1);

namespace App\Exceptions\Support;

/**
 * Error code registry for public API errors.
 *
 * This is the single source of truth for error codes, messages, and metadata.
 * Both ApiErrorFormatter and ScrambleServiceProvider consume this registry.
 */
final class ErrorCode
{
    // Standard HTTP errors
    public const string BAD_REQUEST = 'bad_request';

    public const string UNAUTHENTICATED = 'unauthenticated';

    public const string FORBIDDEN = 'forbidden';

    public const string NOT_FOUND = 'not_found';

    public const string METHOD_NOT_ALLOWED = 'method_not_allowed';

    public const string CONFLICT = 'conflict';

    public const string VALIDATION_ERROR = 'validation_error';

    public const string RATE_LIMITED = 'rate_limited';

    public const string TOKEN_RATE_LIMITED = 'token_rate_limited';

    public const string INTERNAL_SERVER_ERROR = 'internal_server_error';

    public const string SERVICE_UNAVAILABLE = 'service_unavailable';

    // Infrastructure errors
    public const string STORAGE_ERROR = 'storage_error';

    public const string DATABASE_ERROR = 'database_error';

    // Business errors
    public const string PROJECT_ARCHIVED = 'project_archived';

    public const string TASK_ARCHIVED = 'task_archived';

    public const string PLAN_LIMIT_EXCEEDED = 'plan_limit_exceeded';

    public const string SUBSCRIPTION_REQUIRED = 'subscription_required';

    public const string TASK_NOT_TRASHED = 'task_not_trashed';

    public const string INVALID_STATE_TRANSITION = 'invalid_state_transition';

    // Note: Idempotency uses generic error codes (bad_request, conflict, validation_error)
    // as per Phase 3 minimal approach, since the package throws generic HTTP exceptions

    // Service-specific errors (non-public)
    public const string DASHBOARD_SERVICE_ERROR = 'dashboard_service_error';

    private const string DEFAULT_SERVER_ERROR_MESSAGE = 'An unexpected server error occurred.';

    /**
     * @var array<string, array{status: int, message: string, description: string, meta_schema: array<string, string>, example: array<string, mixed>}>
     */
    private const array DEFINITIONS = [
        // Standard HTTP errors
        self::BAD_REQUEST => [
            'status' => 400,
            'message' => 'The request could not be processed.',
            'description' => 'Generic bad request error for malformed or invalid requests.',
            'meta_schema' => [],
            'example' => [
                'message' => 'The request could not be processed.',
                'code' => 'bad_request',
                'errors' => [],
                'meta' => [],
            ],
        ],
        self::UNAUTHENTICATED => [
            'status' => 401,
            'message' => 'Authentication is required.',
            'description' => 'Authentication is required to access this resource.',
            'meta_schema' => [],
            'example' => [
                'message' => 'Authentication is required.',
                'code' => 'unauthenticated',
                'errors' => [],
                'meta' => [],
            ],
        ],
        self::FORBIDDEN => [
            'status' => 403,
            'message' => 'You are not authorized to perform this action.',
            'description' => 'The authenticated user lacks permission to perform this action.',
            'meta_schema' => [],
            'example' => [
                'message' => 'You are not authorized to perform this action.',
                'code' => 'forbidden',
                'errors' => [],
                'meta' => [],
            ],
        ],
        self::NOT_FOUND => [
            'status' => 404,
            'message' => 'Resource not found.',
            'description' => 'The requested resource could not be found.',
            'meta_schema' => [],
            'example' => [
                'message' => 'Resource not found.',
                'code' => 'not_found',
                'errors' => [],
                'meta' => [],
            ],
        ],
        self::METHOD_NOT_ALLOWED => [
            'status' => 405,
            'message' => 'Method not allowed.',
            'description' => 'The HTTP method is not allowed for this resource.',
            'meta_schema' => [],
            'example' => [
                'message' => 'Method not allowed.',
                'code' => 'method_not_allowed',
                'errors' => [],
                'meta' => [],
            ],
        ],
        self::CONFLICT => [
            'status' => 409,
            'message' => 'The request conflicts with the current resource state.',
            'description' => 'The request conflicts with the current state of the target resource.',
            'meta_schema' => [],
            'example' => [
                'message' => 'The request conflicts with the current resource state.',
                'code' => 'conflict',
                'errors' => [],
                'meta' => [],
            ],
        ],
        self::VALIDATION_ERROR => [
            'status' => 422,
            'message' => 'Validation failed.',
            'description' => 'The request failed validation rules.',
            'meta_schema' => [],
            'example' => [
                'message' => 'Validation failed.',
                'code' => 'validation_error',
                'errors' => ['field' => ['The field is required.']],
                'meta' => [],
            ],
        ],
        self::RATE_LIMITED => [
            'status' => 429,
            'message' => 'Too many requests. Please try again later.',
            'description' => 'Rate limit exceeded for the authenticated user.',
            'meta_schema' => ['retry_after_seconds' => 'int'],
            'example' => [
                'message' => 'Too many requests. Please try again later.',
                'code' => 'rate_limited',
                'errors' => [],
                'meta' => ['retry_after_seconds' => 60],
            ],
        ],
        self::TOKEN_RATE_LIMITED => [
            'status' => 429,
            'message' => 'Token rate limit exceeded. Please wait before making more requests.',
            'description' => 'Rate limit exceeded for the specific API token.',
            'meta_schema' => ['retry_after_seconds' => 'int', 'limit' => 'int', 'remaining' => 'int'],
            'example' => [
                'message' => 'Token rate limit exceeded. Please wait before making more requests.',
                'code' => 'token_rate_limited',
                'errors' => [],
                'meta' => ['retry_after_seconds' => 30, 'limit' => 100, 'remaining' => 0],
            ],
        ],
        self::INTERNAL_SERVER_ERROR => [
            'status' => 500,
            'message' => self::DEFAULT_SERVER_ERROR_MESSAGE,
            'description' => 'An unexpected error occurred on the server.',
            'meta_schema' => [],
            'example' => [
                'message' => self::DEFAULT_SERVER_ERROR_MESSAGE,
                'code' => 'internal_server_error',
                'errors' => [],
                'meta' => [],
            ],
        ],
        self::SERVICE_UNAVAILABLE => [
            'status' => 503,
            'message' => 'The service is temporarily unavailable.',
            'description' => 'The service is temporarily unavailable due to maintenance or overload.',
            'meta_schema' => [],
            'example' => [
                'message' => 'The service is temporarily unavailable.',
                'code' => 'service_unavailable',
                'errors' => [],
                'meta' => [],
            ],
        ],

        // Infrastructure errors
        self::STORAGE_ERROR => [
            'status' => 500,
            'message' => 'Storage request could not be completed.',
            'description' => 'An error occurred while accessing storage.',
            'meta_schema' => ['provider' => 'string'],
            'example' => [
                'message' => 'Storage request could not be completed.',
                'code' => 'storage_error',
                'errors' => [],
                'meta' => ['provider' => 's3'],
            ],
        ],
        self::DATABASE_ERROR => [
            'status' => 500,
            'message' => 'A database error occurred. Please try again.',
            'description' => 'A database error occurred while processing the request.',
            'meta_schema' => [],
            'example' => [
                'message' => 'A database error occurred. Please try again.',
                'code' => 'database_error',
                'errors' => [],
                'meta' => [],
            ],
        ],

        // Business errors
        self::PROJECT_ARCHIVED => [
            'status' => 409,
            'message' => 'Project is archived. Restore it before performing this action.',
            'description' => 'The project is archived and cannot be modified in its current state.',
            'meta_schema' => [],
            'example' => [
                'message' => 'Project is archived. Restore it before performing this action.',
                'code' => 'project_archived',
                'errors' => [],
                'meta' => [], // Runtime emits empty metadata
            ],
        ],
        self::TASK_ARCHIVED => [
            'status' => 409,
            'message' => 'Task is archived. Restore it before performing this action.',
            'description' => 'The task is archived and cannot be modified in its current state.',
            'meta_schema' => [],
            'example' => [
                'message' => 'Task is archived. Restore it before performing this action.',
                'code' => 'task_archived',
                'errors' => [],
                'meta' => [],
            ],
        ],
        self::PLAN_LIMIT_EXCEEDED => [
            'status' => 403,
            'message' => 'Plan limit exceeded.',
            'description' => 'The action would exceed the current plan limits.',
            'meta_schema' => [
                'reason' => 'string',
                'limit_type' => 'string',
                'limit_label' => 'string',
                'current_usage' => 'int',
                'max_allowed' => 'int|null',
                'limit_scope' => 'string',
                'can_upgrade' => 'bool',
                'upgrade_required' => 'bool',
            ],
            'example' => [
                'message' => 'Plan limit exceeded.',
                'code' => 'plan_limit_exceeded',
                'errors' => [],
                'meta' => [
                    'reason' => 'Maximum projects reached',
                    'limit_type' => 'projects',
                    'limit_label' => 'Projects',
                    'current_usage' => 5,
                    'max_allowed' => 5,
                    'limit_scope' => 'workspace',
                    'can_upgrade' => true,
                    'upgrade_required' => true, // Match runtime value
                ],
            ],
        ],
        self::SUBSCRIPTION_REQUIRED => [
            'status' => 403,
            'message' => 'Access denied. An active subscription is required to perform this action.',
            'description' => 'The action requires an active subscription.',
            'meta_schema' => ['upgrade_required' => 'bool'],
            'example' => [
                'message' => 'Access denied. An active subscription is required to perform this action.',
                'code' => 'subscription_required',
                'errors' => [],
                'meta' => ['upgrade_required' => true],
            ],
        ],
        self::TASK_NOT_TRASHED => [
            'status' => 403,
            'message' => 'Task must be trashed to perform this action.',
            'description' => 'The task must be in trashed state to perform this action.',
            'meta_schema' => [],
            'example' => [
                'message' => 'Task must be trashed to perform this action.',
                'code' => 'task_not_trashed',
                'errors' => [],
                'meta' => [],
            ],
        ],
        self::INVALID_STATE_TRANSITION => [
            'status' => 422,
            'message' => 'Invalid state transition.',
            'description' => 'The requested state transition is not valid for the current state.',
            'meta_schema' => ['model' => 'string', 'current_state' => 'string', 'attempted_state' => 'string'],
            'example' => [
                'message' => 'Invalid state transition.',
                'code' => 'invalid_state_transition',
                'errors' => [],
                'meta' => ['model' => 'Task', 'current_state' => 'completed', 'attempted_state' => 'in_progress'],
            ],
        ],

        // Service-specific errors (non-public)
        self::DASHBOARD_SERVICE_ERROR => [
            'status' => 500,
            'message' => 'Dashboard service request could not be completed.',
            'description' => 'The dashboard service encountered an error while processing the request.',
            'meta_schema' => [],
            'example' => [
                'message' => 'Dashboard service request could not be completed.',
                'code' => 'dashboard_service_error',
                'errors' => [],
                'meta' => [],
            ],
        ],
    ];

    /**
     * @return array<string, array{status: int, message: string, description: string, meta_schema: array<string, string>, example: array<string, mixed>}>
     */
    public static function all(): array
    {
        return self::DEFINITIONS;
    }

    /**
     * Get error definition by code.
     *
     * @return array{status: int, message: string, description: string, meta_schema: array<string, string>, example: array<string, mixed>}|null
     */
    public static function get(string $code): ?array
    {
        return self::all()[$code] ?? null;
    }

    /**
     * Get status code for an error code.
     */
    public static function status(string $code): int
    {
        return self::get($code)['status'] ?? 500;
    }

    /**
     * Get message for an error code.
     */
    public static function message(string $code): string
    {
        return self::get($code)['message'] ?? self::DEFAULT_SERVER_ERROR_MESSAGE;
    }

    /**
     * Get description for an error code.
     */
    public static function description(string $code): string
    {
        return self::get($code)['description'] ?? 'An error occurred.';
    }

    /**
     * Get example for an error code.
     *
     * @return array<string, mixed>
     */
    public static function example(string $code): array
    {
        return self::get($code)['example'] ?? [];
    }

    /**
     * Get metadata schema for an error code.
     *
     * @return array<string, string>
     */
    public static function metaSchema(string $code): array
    {
        return self::get($code)['meta_schema'] ?? [];
    }

    /**
     * Get default code for a status code.
     */
    public static function defaultCodeForStatus(int $status): string
    {
        return match ($status) {
            400 => self::BAD_REQUEST,
            401 => self::UNAUTHENTICATED,
            403 => self::FORBIDDEN,
            404 => self::NOT_FOUND,
            405 => self::METHOD_NOT_ALLOWED,
            409 => self::CONFLICT,
            422 => self::VALIDATION_ERROR,
            429 => self::RATE_LIMITED, // Default for 429, token-specific uses TOKEN_RATE_LIMITED
            503 => self::SERVICE_UNAVAILABLE,
            default => self::INTERNAL_SERVER_ERROR,
        };
    }
}
