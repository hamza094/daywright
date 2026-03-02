<?php

declare(strict_types=1);

namespace App\Http\Integrations\Paddle\Requests;

use App\DataTransferObjects\Paddle\Data;
use App\DataTransferObjects\Paddle\UserSubscriptionData;
use Override;
use Saloon\Contracts\Body\HasBody;
use Saloon\Enums\Method;
use Saloon\Http\Request;
use Saloon\Http\Response;
use Saloon\PaginationPlugin\Contracts\Paginatable;
use Saloon\Traits\Body\HasJsonBody;

class SubscriptionUsersList extends Request implements HasBody, Paginatable
{
    use HasJsonBody;

    /**
     * The HTTP method of the request
     */
    protected Method $method = Method::POST;

    public function __construct(
        private readonly UserSubscriptionData $UserSubscriptionData,
    ) {}

    /**
     * The endpoint for the request
     */
    #[Override]
    public function resolveEndpoint(): string
    {
        return '/users';
    }

    /**
     * @return array<int, Data>
     */
    #[Override]
    public function createDtoFromResponse(Response $response): mixed
    {
        /** @var array<int, array<string,mixed>> $items */
        $items = (array) ($response->json('response') ?? []);

        return collect($items)
            ->map(fn (array $data): Data => Data::fromResponse($data))
            ->all();
    }

    protected function defaultBody(): array
    {
        return [
            'vendor_id' => $this->UserSubscriptionData->vendorID,
            'vendor_auth_code' => $this->UserSubscriptionData->vendorAuthCode,
            'results_per_page' => $this->UserSubscriptionData->resultsPerPage,
            'page' => 1,
        ];
    }
}
