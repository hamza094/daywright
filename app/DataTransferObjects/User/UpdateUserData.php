<?php

declare(strict_types=1);

namespace App\DataTransferObjects\User;

use Illuminate\Support\Arr;

final readonly class UpdateUserData
{
    /**
     * @param  array<string, mixed>  $userAttributes
     * @param  array<string, mixed>  $infoAttributes
     */
    public function __construct(
        private array $userAttributes,
        private array $infoAttributes,
    ) {}

    /**
     * @param  array<string, mixed>  $payload
     */
    public static function fromArray(array $payload): self
    {
        $userKeys = ['name', 'email', 'username', 'timezone'];

        return new self(
            userAttributes: Arr::only($payload, $userKeys),
            infoAttributes: Arr::except($payload, $userKeys),
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function userAttributes(): array
    {
        return $this->userAttributes;
    }

    /**
     * @return array<string, mixed>
     */
    public function infoAttributes(): array
    {
        return $this->infoAttributes;
    }

    /**
     * @return array{user_attributes: array<string, mixed>, info_attributes: array<string, mixed>}
     */
    public function toArray(): array
    {
        return [
            'user_attributes' => $this->userAttributes,
            'info_attributes' => $this->infoAttributes,
        ];
    }
}
