<?php

declare(strict_types=1);

namespace App\Documentation;

use Illuminate\Routing\Route;
use Illuminate\Support\Facades\Route as RouteFacade;
use Illuminate\Support\Str;

final class PublicApiRouteCatalog
{
    private const array EXCLUDED_PREFIXES = [
        'api/v1/webhooks',
        'api/v1/session',
        'api/v1/auth',
        'api/v1/twofactor',
        'api/v1/register',
        'api/v1/login',
        'api/v1/forgot-password',
        'api/v1/reset-password',
        'api/v1/email',
        'api/v1/logout',
        'api/v1/projects/{project}/export',
        'api/v1/projects/{project}/messages',
        'api/v1/projects/{project}/meetings',
    ];

    /** @var array<string, Route>|null */
    private ?array $routeLookup = null;

    public function isPublished(Route $route): bool
    {
        if ($route->isFallback) {
            return false;
        }

        $uri = $route->uri();
        $middleware = $route->gatherMiddleware();

        // 1. Must be API v1
        if (! Str::startsWith($uri, 'api/v1')) {
            return false;
        }

        // 2. Automatically hides Admin, Token Mgmt, Zoom OAuth, 2FA Mgmt, and Password Update
        if (in_array('session.auth', $middleware, true) || in_array('firstParty.auth', $middleware, true)) {
            return false;
        }

        // 3. Exclude Webhooks, Browser Guest Session/OAuth, and Unreleased Features
        if (Str::startsWith($uri, self::EXCLUDED_PREFIXES)) {
            return false;
        }

        // 4. Exclude singleton HTML form helper routes (/create, /edit)
        return ! Str::endsWith($uri, ['/create', '/edit']);
    }

    public function findForOperation(string $path, string $method): ?Route
    {
        $this->buildRouteLookup();

        $key = $this->normalizeOperationPath($path).':'.mb_strtoupper($method);

        return $this->routeLookup[$key] ?? null;
    }

    private function buildRouteLookup(): void
    {
        if ($this->routeLookup !== null) {
            return;
        }

        $this->routeLookup = [];

        foreach (RouteFacade::getRoutes()->getRoutes() as $route) {
            if (! Str::startsWith($route->uri(), 'api/')) {
                continue;
            }

            $routePath = $this->normalizeOperationPath(
                ltrim(Str::after($route->uri(), 'api'), '/'),
            );

            foreach ($route->methods() as $routeMethod) {
                $this->routeLookup[$routePath.':'.mb_strtoupper($routeMethod)] = $route;
            }
        }
    }

    private function normalizeOperationPath(string $path): string
    {
        // Normalize path to match OpenAPI/Laravel conventions
        // e.g., "v1/projects" vs "projects" should be treated consistently
        $normalized = ltrim($path, '/');

        // Remove v1/ prefix if present
        if (str_starts_with($normalized, 'v1/')) {
            $normalized = mb_substr($normalized, 3);
        }

        return $normalized;
    }
}
