<?php

declare(strict_types=1);

namespace Tests\Feature\Feature\Unit\DataTransferObjects\User;

use App\DataTransferObjects\User\UpdateUserData;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

final class UpdateUserDataTest extends TestCase
{
    #[Test]
    public function it_splits_user_update_payload_by_domain_concern(): void
    {
        $data = UpdateUserData::fromArray([
            'name' => 'Jane Doe',
            'timezone' => null,
            'company' => 'Acme Inc.',
            'bio' => null,
            'current_password' => 'CurrentPassword4!',
            'password' => 'Password4!',
        ]);

        $this->assertSame([
            'user_attributes' => [
                'name' => 'Jane Doe',
                'timezone' => null,
            ],
            'info_attributes' => [
                'company' => 'Acme Inc.',
                'bio' => null,
            ],
            'password' => 'Password4!',
        ], $data->toArray());

        $this->assertTrue($data->hasPasswordUpdate());
    }
}
