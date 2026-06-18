<?php

declare(strict_types=1);

namespace App\Exceptions;

use RuntimeException;

final class UnableToResolveUserIdException extends RuntimeException
{
    public function __construct()
    {
        parent::__construct('Unable to resolve user ID for activity');
    }
}
