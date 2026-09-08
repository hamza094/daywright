<?php

declare(strict_types=1);

namespace App\Providers;

use App\Documentation\PublicApiQueries;
use App\Documentation\PublicApiResponses;
use App\Documentation\PublicApiRouteCatalog;
use App\Documentation\PublicApiTags;
use App\Documentation\ScrambleCompatibility;
use Dedoc\Scramble\Scramble;
use Dedoc\Scramble\Support\Generator\OpenApi;
use Dedoc\Scramble\Support\RouteInfo;
use Illuminate\Routing\Route;
use Illuminate\Support\ServiceProvider;

final class ScrambleServiceProvider extends ServiceProvider
{
    public function boot(
        PublicApiRouteCatalog $routes,
        PublicApiTags $tags,
        PublicApiQueries $queries,
        PublicApiResponses $responses,
        ScrambleCompatibility $compatibility,
    ): void {
        Scramble::resolveTagsUsing(fn (RouteInfo $routeInfo): array => [$tags->resolve($routeInfo)]);

        Scramble::afterOpenApiGenerated(function (OpenApi $openApi) use ($tags, $queries, $responses, $compatibility) {
            $compatibility->applyMethodCorrections($openApi);
            $tags->applyMetadata($openApi);
            $responses->apply($openApi);
            $queries->apply($openApi);
            $compatibility->normalizeServers($openApi);
            $compatibility->applySchemaCorrections($openApi);
        });

        Scramble::routes(fn (Route $route): bool => $routes->isPublished($route));
    }
}
