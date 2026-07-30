<?php

declare(strict_types=1);

namespace App\Traits;

use App\Exceptions\InvalidStateTransitionException;
use BackedEnum;
use Throwable;

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
            // Use case names for user-friendly error messages
            $currentStateName = $this->getStateName($currentStatus, $currentStateValue, $newStatus);
            throw new InvalidStateTransitionException(
                model: $this,
                currentState: $currentStateName,
                attemptedState: $newStatus->name,
            );
        }

        $this->update([$statusColumn => $newStatus]);
    }

    /**
     * Get the readable name for a state value
     */
    private function getStateName(mixed $currentStatus, string|int $currentStateValue, BackedEnum $newStatus): string
    {
        if ($currentStatus instanceof BackedEnum) {
            return $currentStatus->name;
        }

        // Try to find the enum case from the value using the same enum type as newStatus
        try {
            $enumClass = $newStatus::class;
            $enumCase = $enumClass::tryFrom($currentStateValue);
            if ($enumCase !== null) {
                return $enumCase->name;
            }
        } catch (Throwable) {
            // Fallback to string value if enum lookup fails
        }

        return (string) $currentStateValue;
    }
}
