<?php

declare(strict_types=1);

namespace Tests\Feature\Feature\Api;

use Tests\TestCase;

class ApiControllerResponseTest extends TestCase
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
