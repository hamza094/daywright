<?php

declare(strict_types=1);

namespace App\Services\Api\V1\Subscription;

final class SubscriptionCatalogService
{
    /**
     * @return array<int, array{name: string, label: string, interval_label: string, price: int, currency: string, currency_symbol: string, featured: bool}>
     */
    public function availablePlans(): array
    {
        $currency = mb_strtoupper((string) config('services.paddle.prices.currency', 'USD'));

        return [
            $this->plan(
                name: 'monthly',
                label: 'Monthly',
                intervalLabel: 'month',
                price: (int) config('services.paddle.prices.monthly', 12),
                currency: $currency,
                featured: false,
            ),
            $this->plan(
                name: 'yearly',
                label: 'Yearly',
                intervalLabel: 'year',
                price: (int) config('services.paddle.prices.yearly', 100),
                currency: $currency,
                featured: true,
            ),
        ];
    }

    /**
     * @return array{name: string, label: string, interval_label: string, price: int, currency: string, currency_symbol: string, featured: bool}
     */
    private function plan(
        string $name,
        string $label,
        string $intervalLabel,
        int $price,
        string $currency,
        bool $featured,
    ): array {
        return [
            'name' => $name,
            'label' => $label,
            'interval_label' => $intervalLabel,
            'price' => $price,
            'currency' => $currency,
            'currency_symbol' => $this->getSymbolForCurrency($currency),
            'featured' => $featured,
        ];
    }

    private function getSymbolForCurrency(string $currency): string
    {
        // Common currency symbol mapping. Extend as needed.
        return match ($currency) {
            'USD' => '$',
            'EUR' => '€',
            'GBP' => '£',
            'JPY' => '¥',
            'AUD' => '$',
            'CAD' => '$',
            'CHF' => 'CHF',
            'CNY' => '¥',
            'INR' => '₹',
            default => $currency,
        };
    }
}
