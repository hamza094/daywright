<?php

declare(strict_types=1);

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Override;
use Saloon\Config;

abstract class TestCase extends BaseTestCase
{
    use CreatesApplication;

    #[Override]
    protected function setUp(): void
    {
        parent::setUp();
        Config::preventStrayRequests();
    }

    /**
     * @param  array<string, mixed>  $parameters
     * @param  array<string, mixed>  $query
     */
    protected function apiV1Route(string $name, array $parameters = [], array $query = []): string
    {
        return $this->routeWithQuery("api.v1.{$name}", $parameters, $query);
    }

    /**
     * @param  array<string, mixed>  $parameters
     * @param  array<string, mixed>  $query
     */
    protected function apiV1AdminRoute(string $name, array $parameters = [], array $query = []): string
    {
        return $this->routeWithQuery("api.v1.admin.{$name}", $parameters, $query);
    }

    /**
     * @param  array<string, mixed>  $parameters
     * @param  array<string, mixed>  $query
     */
    private function routeWithQuery(string $name, array $parameters = [], array $query = []): string
    {
        $url = route($name, $parameters, false);

        if ($query === []) {
            return $url;
        }

        $queryString = http_build_query($query);

        return $queryString === '' ? $url : "{$url}?{$queryString}";
    }
}
