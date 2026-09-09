<?php

declare(strict_types=1);

namespace Tests\Unit\Documentation;

use App\Documentation\PublicApiRouteCatalog;
use Illuminate\Routing\Route;
use Illuminate\Support\Facades\Route as RouteFacade;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

final class PublicApiRouteCatalogTest extends TestCase
{
    private PublicApiRouteCatalog $catalog;

    protected function setUp(): void
    {
        parent::setUp();

        $this->catalog = new PublicApiRouteCatalog;
    }

    public static function publishedRouteCases(): iterable
    {
        yield 'normal api v1 route' => ['api/v1/catalog-public-route', [], true, false];
        yield 'fallback route' => ['{fallbackPlaceholder}', [], false, true];
        yield 'outside api v1' => ['web/catalog-route', [], false, false];
        yield 'session auth route' => ['api/v1/catalog-session-route', ['session.auth'], false, false];
        yield 'first party auth route' => ['api/v1/catalog-first-party-route', ['firstParty.auth'], false, false];
        yield 'excluded prefix' => ['api/v1/webhooks/catalog', [], false, false];
        yield 'create route' => ['api/v1/catalog/create', [], false, false];
        yield 'edit route' => ['api/v1/catalog/edit', [], false, false];
    }

    #[DataProvider('publishedRouteCases')]
    public function test_route_publication_rules(string $uri, array $middleware, bool $expected, bool $fallback): void
    {
        if ($fallback) {
            RouteFacade::fallback(fn (): string => 'not found');
        } else {
            RouteFacade::get($uri, fn (): string => 'ok')->middleware($middleware);
        }

        $route = $this->findRouteByUri($uri);
        $this->assertNotNull($route);

        $this->assertSame($expected, $this->catalog->isPublished($route));
    }

    public function test_find_for_operation_matches_normalized_path_and_method(): void
    {
        RouteFacade::get('api/v1/projects', fn (): string => 'ok');

        $route = $this->catalog->findForOperation('projects', 'GET');
        $this->assertNotNull($route);
        $this->assertTrue(in_array('GET', $route->methods(), true));
    }

    public function test_find_for_operation_rejects_wrong_method(): void
    {
        RouteFacade::get('api/v1/test-unique-route', fn (): string => 'ok');

        $route = $this->catalog->findForOperation('test-unique-route', 'POST');
        $this->assertNull($route, 'Should not find route with wrong method');
    }

    public function test_find_for_operation_handles_v1_prefix_normalization(): void
    {
        RouteFacade::get('api/v1/projects', fn (): string => 'ok');

        // Both normalized and non-normalized paths should work
        $route1 = $this->catalog->findForOperation('projects', 'GET');
        $route2 = $this->catalog->findForOperation('v1/projects', 'GET');

        $this->assertNotNull($route1);
        $this->assertNotNull($route2);
    }

    private function findRouteByUri(string $uri): ?Route
    {
        foreach (RouteFacade::getRoutes()->getRoutes() as $route) {
            if ($route->uri() === $uri) {
                return $route;
            }
        }

        return null;
    }
}
