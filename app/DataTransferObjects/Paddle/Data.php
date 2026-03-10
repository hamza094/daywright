<?php

declare(strict_types=1);

namespace App\DataTransferObjects\Paddle;

use Carbon\Carbon;

final class Data
{
    public function __construct(
        public int $userId,
        public string $email,
        public string $signUpDate,
        public string $lastPaymentAmount,
        public string $lastPaymentCurrency,
        public string $lastPaymentDate,
        public string $nextPaymentDate,
    ) {}

    /* public static function fromResponse(array $response): static
    {
     return new static(
       marketing_consent: $response['marketing_consent'],
       userId: $response['user_id'],
       email: $response['user_email'],
       state: $response['state'],
       //fullName: $response['full_name'],
       //private: $response['private'],
       //description: $response['description'] ?? '',
       //createdAt: Carbon::parse($response['created_at']),
 );
}*/

}
