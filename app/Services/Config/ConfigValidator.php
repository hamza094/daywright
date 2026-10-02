<?php

declare(strict_types=1);

namespace App\Services\Config;

use LogicException;

final class ConfigValidator
{
    public static bool $overrideConsoleCheckForTests = false;

    public function validatePaddleConfig(): void
    {
        if (! app()->isProduction() || (app()->runningInConsole() && ! self::$overrideConsoleCheckForTests)) {
            return;
        }

        $errors = [];

        if (blank(config('cashier.public_key'))) {
            $errors[] = 'PADDLE_PUBLIC_KEY';
        }

        if (blank(config('cashier.webhook'))) {
            $errors[] = 'CASHIER_WEBHOOK (cashier.webhook)';
        } elseif (! str_starts_with((string) config('cashier.webhook'), 'https://')) {
            $errors[] = 'CASHIER_WEBHOOK must be HTTPS';
        } elseif (! str_ends_with((string) config('cashier.webhook'), '/paddle/webhook')) {
            $errors[] = 'CASHIER_WEBHOOK must end with /paddle/webhook';
        }

        if (blank(config('services.paddle.monthly'))) {
            $errors[] = 'Monthly_Plan (services.paddle.monthly)';
        }

        if (blank(config('services.paddle.yearly'))) {
            $errors[] = 'Yearly_Plan (services.paddle.yearly)';
        }

        if (blank(config('services.paddle.subscription_name'))) {
            $errors[] = 'PADDLE_SUBSCRIPTION_NAME (services.paddle.subscription_name)';
        }

        if (blank(config('services.paddle.vendor_id'))) {
            $errors[] = 'PADDLE_VENDOR_ID (services.paddle.vendor_id)';
        }

        if (blank(config('services.paddle.vendor_auth_code'))) {
            $errors[] = 'PADDLE_VENDOR_AUTH_CODE (services.paddle.vendor_auth_code)';
        }

        if ($errors !== []) {
            throw new LogicException(
                'The following Paddle configuration values are missing in production environment: '.implode(', ', $errors)
            );
        }
    }
}
