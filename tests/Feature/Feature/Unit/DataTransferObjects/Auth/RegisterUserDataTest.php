<?php

declare(strict_types=1);

namespace Tests\Feature\Feature\Unit\DataTransferObjects\Auth;

use App\DataTransferObjects\Auth\RegisterUserData;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

final class RegisterUserDataTest extends TestCase
{
    #[Test]
    public function it_builds_registration_attributes_from_a_payload(): void
    {
        $data = RegisterUserData::fromArray([
            'name' => 'Berry',
            'email' => 'berry@example.com',
            'password' => 'Password4!',
        ]);

        $this->assertSame([
            'name' => 'Berry',
            'email' => 'berry@example.com',
            'password' => 'hashed-password',
        ], $data->toUserAttributes('hashed-password'));
    }
}
