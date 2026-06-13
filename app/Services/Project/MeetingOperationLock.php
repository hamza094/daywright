<?php

declare(strict_types=1);

namespace App\Services\Project;

use Closure;
use Illuminate\Contracts\Cache\LockTimeoutException;
use Illuminate\Support\Facades\Cache;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;

final class MeetingOperationLock
{
    private const int LOCK_SECONDS = 120;

    private const int LOCK_WAIT_SECONDS = 10;

    /**
     * @template TReturn
     *
     * @param  Closure(): TReturn  $callback
     * @return TReturn
     */
    public function block(string $key, string $conflictMessage, Closure $callback): mixed
    {
        try {
            return Cache::lock($key, self::LOCK_SECONDS)
                ->block(self::LOCK_WAIT_SECONDS, fn (): mixed => $callback());
        } catch (LockTimeoutException $exception) {
            throw new ConflictHttpException($conflictMessage, $exception);
        }
    }
}
