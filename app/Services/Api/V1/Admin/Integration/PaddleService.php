<?php

declare(strict_types=1);

namespace App\Services\Api\V1\Admin\Integration;

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
            $subscription['user_id'] ?? 0,
            $subscription['user_email'] ?? 0,
            $subscription['signup_date'] ?? 0,
            $subscription['last_payment']['amount'] ?? 0,
            $subscription['last_payment']['currency'] ?? 0,
            $subscription['last_payment']['date'] ?? 0,
            $subscription['next_payment']['date'] ?? 0,
        ))->filter();

        return DataCollection::make($filteredSubscriptions);
    }

    private function connector(): PaddleConnector
    {
        return app(PaddleConnector::class);
    }
}
