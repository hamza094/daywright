<?php

declare(strict_types=1);

namespace App\Services\Admin\Integration;

use App\Collections\Paddle\DataCollection;
use App\DataTransferObjects\Paddle\Data;
use App\DataTransferObjects\Paddle\UserSubscriptionData;
use App\Http\Integrations\Paddle\PaddleConnector;
use App\Http\Integrations\Paddle\Requests\SubscriptionUsersList;
use App\Interfaces\PaddleApi;
use Override;

final class PaddleService implements PaddleApi
{
    #[Override]
    public function subscriptionUsersList(UserSubscriptionData $listData): DataCollection
    {
        $subscriptionsData = $this->connector()
            ->send(new SubscriptionUsersList($listData))
            ->collect();

        $subscriptions = collect($subscriptionsData['response']);

        $filteredSubscriptions = $subscriptions->map(fn ($subscription): Data => new Data(
            (int) ($subscription['user_id'] ?? 0),
            (string) ($subscription['user_email'] ?? ''),
            (string) ($subscription['signup_date'] ?? ''),
            (string) ($subscription['last_payment']['amount'] ?? ''),
            (string) ($subscription['last_payment']['currency'] ?? ''),
            (string) ($subscription['last_payment']['date'] ?? ''),
            (string) ($subscription['next_payment']['date'] ?? ''),
        ))->filter();

        return DataCollection::make($filteredSubscriptions);
    }

    private function connector(): PaddleConnector
    {
        return app(PaddleConnector::class);
    }
}
