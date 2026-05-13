<?php

declare(strict_types=1);

namespace App\Http\Resources\Api\V1\Auth;

use Dedoc\Scramble\Attributes\SchemaName;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Override;

#[SchemaName('TwoFactorChallenge')]
class TwoFactorChallengeResource extends JsonResource
{
    public function __construct(
        private readonly string $state = '2fa_required',
        private readonly string $message = 'Two-factor authentication is enabled. Please provide the verification code.',
    ) {
        parent::__construct(null);
    }

    /**
     * Transform the resource into an array.
     *
     * @return array<string, string>
     */
    #[Override]
    public function toArray(Request $request): array
    {
        return [
            /**
             * Indicates that the client must complete the two-factor login step.
             *
             * @example 2fa_required
             */
            'two_factor_state' => $this->state,
            /**
             * Human-readable next-step guidance for the client.
             *
             * @example Two-factor authentication is enabled. Please provide the verification code.
             */
            'message' => $this->message,
        ];
    }
}
