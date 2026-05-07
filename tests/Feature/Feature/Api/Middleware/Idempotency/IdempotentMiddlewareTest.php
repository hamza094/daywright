<?php

declare(strict_types=1);

namespace Tests\Feature\Feature\Api\Middleware\Idempotency;

use Tests\TestCase;

class IdempotentMiddlewareTest extends TestCase
{
    /**
     * A basic feature test example.
     *
     * @return void
     */
    public function test_example()
    {
        $response = $this->get('/');

        $response->assertStatus(200);
    }
}
