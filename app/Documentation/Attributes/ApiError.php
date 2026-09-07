<?php

declare(strict_types=1);

namespace App\Documentation\Attributes;

use App\Exceptions\Support\ErrorCode;
use Attribute;
use InvalidArgumentException;

/**
 * Documents a confirmed business error emitted by an API operation.
 *
 * The error code must exist in the public ErrorCode registry.
 */
#[Attribute(Attribute::IS_REPEATABLE | Attribute::TARGET_METHOD)]
final readonly class ApiError
{
    public function __construct(
        public string $code,
    ) {
        if (ErrorCode::get($code) === null) {
            throw new InvalidArgumentException("Unknown public API error code [{$code}].");
        }
    }
}
