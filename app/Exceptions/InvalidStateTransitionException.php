<?php

declare(strict_types=1);

namespace App\Exceptions;

use Illuminate\Database\Eloquent\Model;
use Override;

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

    #[Override]
    public function publicMessage(): string
    {
        return "Invalid state transition from {$this->currentState} to {$this->attemptedState}";
    }

    /**
     * @return array<string, mixed>
     */
    #[Override]
    public function meta(\Illuminate\Http\Request $request): array
    {
        return [
            'model' => $this->model::class,
            'current_state' => $this->currentState,
            'attempted_state' => $this->attemptedState,
        ];
    }
}
