<?php

declare(strict_types=1);

namespace Tests\Unit\Enums;

use App\Enums\ApiScope;
use PHPUnit\Framework\TestCase;

class ApiScopeTest extends TestCase
{
    public function test_it_returns_all_valid_values(): void
    {
        $values = ApiScope::values();

        $this->assertContains('projects:read', $values);
        $this->assertContains('account:write', $values);
        $this->assertCount(7, $values);
    }

    public function test_it_validates_scopes_correctly(): void
    {
        $this->assertTrue(ApiScope::allValid(['projects:read', 'team:write']));
        $this->assertFalse(ApiScope::allValid(['projects:read', 'invalid:scope']));
    }
}
