<?php

declare(strict_types=1);

namespace Tests\Unit\Paddle;

use App\DataTransferObjects\Paddle\Data;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class DataDtoTest extends TestCase
{
    #[Test]
    public function from_response_maps_paddle_payload_into_dto_shape(): void
    {
        $response = [
            'user_id' => 42,
            'user_email' => 'alice@example.com',
            'signup_date' => '2026-01-01',
            'last_payment' => [
                'amount' => '1099',
                'currency' => 'USD',
                'date' => '2026-02-01',
            ],
            'next_payment' => [
                'date' => '2026-03-01',
            ],
        ];

        $data = Data::fromResponse($response);

        $this->assertSame(42, $data->userId);
        $this->assertSame('alice@example.com', $data->email);
        $this->assertSame('2026-01-01', $data->signUpDate);
        $this->assertSame('1099', $data->lastPaymentAmount);
        $this->assertSame('USD', $data->lastPaymentCurrency);
        $this->assertSame('2026-02-01', $data->lastPaymentDate);
        $this->assertSame('2026-03-01', $data->nextPaymentDate);

        // Serialization via toArray() is optional; primary contract is fromResponse()
    }
}
