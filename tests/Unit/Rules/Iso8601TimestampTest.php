<?php

declare(strict_types=1);

namespace Tests\Unit\Rules;

use App\Rules\Iso8601Timestamp;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class Iso8601TimestampTest extends TestCase
{
    #[Test]
    public function it_normalizes_valid_timestamps_to_utc(): void
    {
        $this->assertSame(
            '2026-06-01T12:34:56+00:00',
            Iso8601Timestamp::normalizeToUtc('2026-06-01T14:34:56+02:00'),
        );
    }

    #[Test]
    public function it_returns_null_for_invalid_timestamps(): void
    {
        $this->assertNull(Iso8601Timestamp::normalizeToUtc('2026-13-01T14:34:56+02:00'));
    }
}
