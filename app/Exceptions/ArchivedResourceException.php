<?php

declare(strict_types=1);

namespace App\Exceptions;

use Illuminate\Contracts\Debug\ShouldntReport;
use Override;
use Symfony\Component\HttpFoundation\Response;

final class ArchivedResourceException extends ApiException implements ShouldntReport
{
    public const string RESOURCE_PROJECT = 'project';

    public const string RESOURCE_TASK = 'task';

    private function __construct(
        private readonly string $resourceType,
    ) {
        parent::__construct();
    }

    public static function project(): self
    {
        return new self(self::RESOURCE_PROJECT);
    }

    public static function task(): self
    {
        return new self(self::RESOURCE_TASK);
    }

    public function errorCode(): string
    {
        return sprintf('%s_archived', $this->resourceType);
    }

    public function status(): int
    {
        return Response::HTTP_CONFLICT;
    }

    #[Override]
    protected function defaultMessage(): string
    {
        return sprintf('%s is archived. Restore it before performing this action.', ucfirst($this->resourceType));
    }
}
