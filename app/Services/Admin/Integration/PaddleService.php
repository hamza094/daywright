<?php

declare(strict_types=1);

namespace App\Services\Admin\Integration;

use App\Collections\Paddle\DataCollection;
use App\DataTransferObjects\Paddle\Data;
use App\DataTransferObjects\Paddle\UserSubscriptionData;
use App\Exceptions\Paddle\PaddleRequestException;
use App\Http\Integrations\Paddle\PaddleConnector;
use App\Http\Integrations\Paddle\Requests\SubscriptionUsersList;
use App\Interfaces\PaddleApi;
use Override;
use Throwable;

final class PaddleService implements PaddleApi
{
    #[Override]
    public function subscriptionUsersList(UserSubscriptionData $listData): DataCollection
    {
        try {
            /** @var array<string, mixed> $subscriptionsData */
            $subscriptionsData = $this->connector()
                ->send(new SubscriptionUsersList($listData))
                ->collect();
        } catch (Throwable $exception) {
            throw new PaddleRequestException(message: $exception->getMessage());
        }

        /** @var array<int, array<string,mixed>> $subscriptionsArray */
        $subscriptionsArray = $subscriptionsData['response'] ?? [];

        /** @var \Illuminate\Support\Collection<int, array<string,mixed>> $subscriptions */
        $subscriptions = collect($subscriptionsArray);

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
