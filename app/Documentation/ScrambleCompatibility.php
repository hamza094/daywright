<?php

declare(strict_types=1);

namespace App\Documentation;

use Dedoc\Scramble\Support\Generator\OpenApi;
use Dedoc\Scramble\Support\Generator\Types\BooleanType;
use Dedoc\Scramble\Support\Generator\Types\ObjectType;
use Illuminate\Routing\Route;
use Illuminate\Support\Str;

final class ScrambleCompatibility
{
    public function __construct(
        private readonly PublicApiRouteCatalog $routeCatalog,
    ) {}

    /**
     * Scramble emits one operation for Laravel's combined PUT|PATCH resource route.
     * Keep both registered public methods visible to API consumers.
     */
    public function applyMethodCorrections(OpenApi $openApi): void
    {
        foreach ($openApi->paths as $path) {
            if (! isset($path->operations['put'])
                || isset($path->operations['patch'])) {
                continue;
            }

            $route = $this->routeCatalog->findForOperation($path->path, 'put');

            if (! $route || ! in_array('PATCH', $route->methods(), true)) {
                continue;
            }

            $patchOperation = clone $path->operations['put'];
            $patchOperation->setMethod('patch');
            $patchOperation->setOperationId(
                $patchOperation->operationId ? $patchOperation->operationId.'.patch' : null,
            );
            $path->operations['patch'] = $patchOperation;
        }
    }

    /**
     * Normalize server URLs to relative paths for documentation.
     */
    public function normalizeServers(OpenApi $openApi): void
    {
        $applicationUrl = rtrim(url('/'), '/');

        foreach ($openApi->servers as $server) {
            if (! str_starts_with($server->url, $applicationUrl)) {
                continue;
            }

            $relativeUrl = Str::after($server->url, $applicationUrl);
            $server->url = $relativeUrl === '' ? '/' : $relativeUrl;
        }
    }

    /**
     * Scramble does not fully support Laravel Pennant feature flags.
     */
    public function applySchemaCorrections(OpenApi $openApi): void
    {
        if (! isset($openApi->components->schemas['FeatureFlagsResource'])) {
            return;
        }

        // Fix FeatureFlagsResource to use boolean additionalProperties
        $featureFlagsSchema = $openApi->components->schemas['FeatureFlagsResource'];
        $featureFlagsSchema->type = (new ObjectType)->additionalProperties(new BooleanType);
    }
}
