<?php

declare(strict_types=1);

namespace App\DataTransferObjects\Paddle;

final readonly class Data
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

    /**
     * @param  array<string, mixed>  $response
     */
    public static function fromResponse(array $response): static
    {
        /** @var array<string, mixed> $lastPayment */
        $lastPayment = (array) ($response['last_payment'] ?? []);

        /** @var array<string, mixed> $nextPayment */
        $nextPayment = (array) ($response['next_payment'] ?? []);

        return new self(
            userId: (int) ($response['user_id'] ?? 0),
            email: (string) ($response['user_email'] ?? ''),
            signUpDate: (string) ($response['signup_date'] ?? ''),
            lastPaymentAmount: (string) ($lastPayment['amount'] ?? ''),
            lastPaymentCurrency: (string) ($lastPayment['currency'] ?? ''),
            lastPaymentDate: (string) ($lastPayment['date'] ?? ''),
            nextPaymentDate: (string) ($nextPayment['date'] ?? ''),
        );
    }
}
