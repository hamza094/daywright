<?php

declare(strict_types=1);

namespace App\Traits;

use App\Exceptions\InvalidStateTransitionException;
use BackedEnum;

trait HasStateMachine
{
    /**
     * Define the valid state transitions for the model.
     *
     * @return array<string, list<string>>
     */
    abstract protected function validTransitions(): array;

    /**
     * Transition the model to a new state with validation.
     *
     *
     * @throws InvalidStateTransitionException
     */
    public function transitionTo(BackedEnum $newStatus, string $statusColumn = 'status'): void
    {
        $currentStatus = $this->getAttribute($statusColumn);
        $currentStateValue = $currentStatus instanceof BackedEnum ? $currentStatus->value : $currentStatus;

        $allowed = $this->validTransitions()[$currentStateValue] ?? [];

        if (! in_array($newStatus->value, $allowed, strict: true)) {
            throw new InvalidStateTransitionException(
                model: $this,
                currentState: is_string($currentStateValue) ? $currentStateValue : (string) $currentStateValue,
                attemptedState: $newStatus->value,
            );
        }

        $this->update([$statusColumn => $newStatus]);
    }
}
