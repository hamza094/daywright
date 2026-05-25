<?php

declare(strict_types=1);

namespace Tests\Feature\Unit\DataTransferObjects;

use Tests\TestCase;

class ProjectTaskCommandDataTest extends TestCase
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
