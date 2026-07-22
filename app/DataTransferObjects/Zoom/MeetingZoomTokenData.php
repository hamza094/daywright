<?php

declare(strict_types=1);

namespace App\DataTransferObjects\Zoom;

use App\Enums\Meeting\MeetingTokenAction;

final readonly class MeetingZoomTokenData
{
    public function __construct(
        public MeetingTokenAction $action,
    ) {}

    /**
     * @param  array<string, mixed>  $validated
     */
    public static function fromValidated(array $validated): self
    {
        return new self(
            action: MeetingTokenAction::from($validated['action']),
        );
    }

    /**
     * Convert the DTO to the exact array shape expected by the API / Database.
     *
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'action' => $this->action->value,
        ];
    }
}
