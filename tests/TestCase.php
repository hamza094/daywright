<?php

declare(strict_types=1);

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Support\Str;
use Override;
use Saloon\Config;

abstract class TestCase extends BaseTestCase
{
    use CreatesApplication;

    #[Override]
    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        Config::preventStrayRequests();
        // Ensure tests that generate Zoom JWTs have a sufficiently long secret
        \Illuminate\Support\Facades\Config::set('services.zoom.client_id', 'fake-key');
        \Illuminate\Support\Facades\Config::set('services.zoom.client_secret', str_repeat('a', 64));
    }

    /**
     * @param  array<string, mixed>  $parameters
     * @param  array<string, mixed>  $query
     */
    protected function apiV1Route(string $name, array $parameters = [], array $query = []): string
    {
        return $this->apiRoute('v1', $name, $parameters, $query);
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
    protected function apiV1ProjectRoute(string $name, mixed $project, array $parameters = [], array $query = []): string
    {
        return $this->apiV1Route($name, array_merge(['project' => $project], $parameters), $query);
    }

    /**
     * @param  array<string, mixed>  $query
     */
    protected function apiV1ProjectTaskRoute(string $name, mixed $project, mixed $task, array $query = []): string
    {
        return $this->apiV1Route($name, [
            'project' => $project,
            'task' => $task,
        ], $query);
    }

    /**
     * @param  array<string, mixed>  $parameters
     * @param  array<string, mixed>  $query
     */
    protected function apiV1ProjectUserRoute(string $name, mixed $project, mixed $user, array $parameters = [], array $query = []): string
    {
        return $this->apiV1Route($name, array_merge([
            'project' => $project,
            'user' => $user,
        ], $parameters), $query);
    }

    /**
     * @param  array<string, mixed>  $parameters
     * @param  array<string, mixed>  $query
     */
    protected function apiRoute(string $version, string $name, array $parameters = [], array $query = []): string
    {
        return $this->routeWithQuery("api.{$version}.{$name}", $parameters, $query);
    }

    /**
     * @return array<string, string>
     */
    protected function idempotencyHeaders(?string $key = null): array
    {
        return [
            'Idempotency-Key' => $key ?? (string) Str::uuid(),
        ];
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
