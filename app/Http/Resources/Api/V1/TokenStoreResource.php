<?php

declare(strict_types=1);

namespace App\Http\Resources\Api\V1;

use Dedoc\Scramble\Attributes\SchemaName;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Laravel\Sanctum\PersonalAccessToken;
use Override;

#[SchemaName('TokenStoreResponse')]
class TokenStoreResource extends JsonResource
{
    public function __construct(
        private readonly string $token,
        private readonly PersonalAccessToken $accessToken,
    ) {
        parent::__construct($accessToken);
    }

    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    #[Override]
    public function toArray(Request $request): array
    {
        return [
            'token' => $this->token,
            'token_resource' => new TokenResource($this->accessToken),
        ];
    }
}
