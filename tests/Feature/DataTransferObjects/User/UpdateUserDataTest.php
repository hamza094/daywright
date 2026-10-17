<?php

declare(strict_types=1);

namespace Tests\Feature\DataTransferObjects\User;

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
        ], $data->toArray());
    }

    #[Test]
    public function it_excludes_email_from_user_attributes_for_security(): void
    {
        $data = UpdateUserData::fromArray([
            'name' => 'Jane Doe',
            'email' => 'jane@example.com',
            'timezone' => 'America/New_York',
            'company' => 'Acme Inc.',
        ]);

        $this->assertSame([
            'user_attributes' => [
                'name' => 'Jane Doe',
                'timezone' => 'America/New_York',
            ],
            'info_attributes' => [
                'company' => 'Acme Inc.',
            ],
        ], $data->toArray());

        // Verify email is excluded from user attributes
        $this->assertArrayNotHasKey('email', $data->userAttributes());
    }
}
