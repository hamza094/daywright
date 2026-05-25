<?php

declare(strict_types=1);

namespace Tests\Feature\Feature\Api\V1;

use Tests\TestCase;

class QueryCompatibilityDiscoveryTest extends TestCase
{
    /**
     * A basic feature test example.
     */
    public function test_example(): void
    {
        $response = $this->get('/');

        $response->assertStatus(200);
    }
}
