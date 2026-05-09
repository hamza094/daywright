<?php

declare(strict_types=1);

namespace App\Exceptions;

use App\Exceptions\Support\ApiErrorFormatter;
use Illuminate\Http\Request;
use RuntimeException;

abstract class ApiException extends RuntimeException
{
    abstract public function status(): int;

    abstract public function errorCode(): string;

    public function publicMessage(): string
    {
        return ApiErrorFormatter::publicMessage($this->getMessage(), $this->defaultMessage());
    }

    /**
     * @return array<string, array<int, string>>
     */
    public function errors(): array
    {
        return [];
    }

    /**
     * @return array<string, mixed>
     */
    public function meta(Request $request): array
    {
        return [];
    }

    protected function defaultMessage(): string
    {
        return ApiErrorFormatter::defaultMessageForStatus($this->status());
    }
}
