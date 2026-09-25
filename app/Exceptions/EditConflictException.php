<?php

declare(strict_types=1);

namespace App\Exceptions;

use App\Exceptions\Support\ErrorCode;
use Illuminate\Http\Request;
use Override;

final class EditConflictException extends ApiException
{
    public function __construct(
        private readonly int $expectedVersion,
        private readonly int $currentVersion,
        string $message = 'The resource was modified by another user.'
    ) {
        parent::__construct($message);
    }

    public function status(): int
    {
        return 409; // Conflict
    }

    public function errorCode(): string
    {
        return ErrorCode::EDIT_CONFLICT;
    }

    #[Override]
    public function meta(Request $request): array
    {
        return [
            'expected_version' => $this->expectedVersion,
            'current_version' => $this->currentVersion,
        ];
    }
}
