<?php

declare(strict_types=1);

namespace App\Exceptions;

use Illuminate\Database\Eloquent\Model;

final class InvalidStateTransitionException extends ApiException
{
    public function __construct(
        private readonly Model $model,
        private readonly string $currentState,
        private readonly string $attemptedState,
        string $message = '',
    ) {
        parent::__construct($message ?: "Cannot transition from {$currentState} to {$attemptedState}");
    }

    public function status(): int
    {
        return 422;
    }

    public function errorCode(): string
    {
        return 'invalid_state_transition';
    }

    public function publicMessage(): string
    {
        return "Invalid state transition from {$this->currentState} to {$this->attemptedState}";
    }

    /**
     * @return array<string, mixed>
     */
    public function meta(\Illuminate\Http\Request $request): array
    {
        return [
            'model' => get_class($this->model),
            'current_state' => $this->currentState,
            'attempted_state' => $this->attemptedState,
        ];
    }
}
