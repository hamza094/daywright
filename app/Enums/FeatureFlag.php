<?php

declare(strict_types=1);

namespace App\Enums;

use Illuminate\Support\Str;

enum FeatureFlag: string
{
    case ProjectExport = 'project-export';
    case ProjectMessaging = 'project-messaging';

    public function key(): string
    {
        return Str::snake($this->name);
    }

    public function pennantName(): string
    {
        return $this->value;
    }

    /**
     * Whether this flag is safe to expose to client apps by default.
     *
     * Keep this `false` by default for security; opt-in per-case below.
     */
    public function visibleToClient(): bool
    {
        // Grouped match is more explicit; suppress PHPStan false-positive for the following arm.
        return match ($this) {
            // @phpstan-ignore-next-line
            self::ProjectExport, self::ProjectMessaging => true,
            default => false,
        };
    }
}
