# Scramble Service Provider Maintainability Plan

## Goal

Refactor the 858-line `app/Providers/ScrambleServiceProvider.php` into five cohesive documentation classes while preserving the generated OpenAPI contract.

The provider remains an explicit composition root. This is a structural refactor: it must not intentionally change routes, schemas, parameters, tags, responses, examples, security, or the tracked `api.json`.

## Simplicity rules

- Create only the five classes listed here.
- Do not introduce interfaces, a generic pipeline, DTOs, factories, repositories, actions, or a configuration class.
- Use concrete classes and Laravel automatic dependency injection in `boot()`.
- Give each class a small public API and keep helpers private.
- Keep constants beside the logic that uses them.
- Keep transformation order visible in the provider.
- Continue using `App\Exceptions\Support\ErrorCode` as the error source of truth.
- Continue using explicit `#[ApiError(...)]` attributes for uncommon business errors.
- Preserve middleware-derived documentation in `PublicApiMiddlewareResponses`.
- Do not refactor `PublicApiMiddlewareResponses` in this work.
- Do not combine this refactor with API contract changes.
- Do not leave old implementations commented out. Git provides rollback history.

## Target structure

```text
app/Documentation/
├── Attributes/
├── Transformers/
│   └── PublicApiMiddlewareResponses.php
├── PublicApiRouteCatalog.php
├── PublicApiTags.php
├── PublicApiQueries.php
├── PublicApiResponses.php
└── ScrambleCompatibility.php

app/Providers/
└── ScrambleServiceProvider.php
```

The intended public methods are:

```php
final class PublicApiRouteCatalog
{
    public function isPublished(Route $route): bool;

    public function findForOperation(string $path, string $method): ?Route;
}

final class PublicApiTags
{
    public function resolve(RouteInfo $routeInfo): string;

    public function applyMetadata(OpenApi $openApi): void;
}

final class PublicApiQueries
{
    public function apply(OpenApi $openApi): void;
}

final class PublicApiResponses
{
    public function apply(OpenApi $openApi): void;
}

final class ScrambleCompatibility
{
    public function applyMethodCorrections(OpenApi $openApi): void;

    public function normalizeServers(OpenApi $openApi): void;

    public function applySchemaCorrections(OpenApi $openApi): void;
}
```

Method names may be adjusted slightly for clarity. Do not make internal helpers public for testing.

## Required safety checks

Run these existing tests after every extraction phase:

```powershell
php artisan test tests/Feature/Api/Contracts/Docs/ScrambleDocsTest.php
php artisan test tests/Feature/Api/Contracts/Docs/RuntimeOpenApiParityTest.php
```

Export the generated document to a temporary path after every phase:

```powershell
php artisan scramble:export --path=storage/app/openapi-refactor-current.json
```

Compare it with the Phase 0 baseline by decoding JSON so formatting differences do not matter:

```powershell
php -r '$before=json_decode(file_get_contents("storage/app/openapi-refactor-before.json"),true,512,JSON_THROW_ON_ERROR); $current=json_decode(file_get_contents("storage/app/openapi-refactor-current.json"),true,512,JSON_THROW_ON_ERROR); exit($before==$current ? 0 : 1);'
```

If comparison fails, inspect and fix the extraction before continuing. Do not update tracked `api.json` to hide a difference.

Only one new test file is required:

```text
tests/Unit/Documentation/PublicApiRouteCatalogTest.php
```

Existing documentation tests already cover tags, query parameters, response schemas, middleware contracts, method parity, and the generated public surface. Additional unit tests are unnecessary unless extraction exposes untested behavior.

## Phase 0 — Capture current behavior

No application code changes.

1. Record current working-tree status. `ScrambleServiceProvider.php` already has user changes, so preserve them.
2. Run the two existing documentation tests.
3. Export the current generated contract:

   ```powershell
   php artisan scramble:export --path=storage/app/openapi-refactor-before.json
   ```

4. Record path count, tag count, server URL, version, and test results.
5. Do not overwrite `api.json`.

### Complete when

- Both documentation tests pass.
- `storage/app/openapi-refactor-before.json` represents the current working tree.
- Baseline results are recorded.

## Phase 1 — Extract PublicApiRouteCatalog

This goes first because response and compatibility code both require route lookup.

### Files

- Create `app/Documentation/PublicApiRouteCatalog.php`.
- Modify `app/Providers/ScrambleServiceProvider.php`.
- Create `tests/Unit/Documentation/PublicApiRouteCatalogTest.php`.

### Move

- The `Scramble::routes()` publication predicate.
- Fallback-route exclusion.
- The `api/v1` boundary.
- Session and first-party middleware exclusion.
- Excluded URI prefixes.
- `/create` and `/edit` exclusion.
- `findPublicRouteForOperation()`.
- OpenAPI/Laravel path normalization used by route lookup.

Keep excluded prefixes as a private constant in `PublicApiRouteCatalog`.

Avoid repeated route scans by creating a private lookup lazily when `findForOperation()` is first called. Do not create a separate resolver class.

The provider uses:

```php
Scramble::routes(
    fn (Route $route): bool => $routes->isPublished($route),
);
```

### Required new test

Use one compact data provider covering:

- A normal public `api/v1` route is included.
- A fallback route is excluded.
- A route outside `api/v1` is excluded.
- A `session.auth` route is excluded.
- A `firstParty.auth` route is excluded.
- An explicitly excluded prefix is excluded.
- `/create` and `/edit` are excluded.
- `findForOperation()` matches normalized path and method and rejects a wrong method.

Do not create one test method per excluded URI prefix.

### Verification

Run the new unit test, both documentation tests, export, and semantic comparison.

### Complete when

- Route selection and lookup no longer live in the provider.
- All checks pass with no generated contract change.

## Phase 2 — Extract PublicApiTags

### Files

- Create `app/Documentation/PublicApiTags.php`.
- Modify `app/Providers/ScrambleServiceProvider.php`.

### Move

- `resolvePublicApiTag()`.
- `publicApiTagDefinitions()`.
- `applyPublicApiTagMetadata()`.

Keep tag definitions as a private constant or method inside `PublicApiTags`. Do not move them into Laravel configuration.

The provider registers:

```php
Scramble::resolveTagsUsing(
    fn (RouteInfo $routeInfo): array => [$tags->resolve($routeInfo)],
);
```

### Tests

Add no test. `ScrambleDocsTest` already verifies the tag list, order, and representative route classification.

### Verification

Run both documentation tests, export, and semantic comparison.

### Complete when

- Tag classification and metadata no longer live in the provider.
- All checks pass with no generated contract change.

## Phase 3 — Extract PublicApiQueries

### Files

- Create `app/Documentation/PublicApiQueries.php`.
- Modify `app/Providers/ScrambleServiceProvider.php`.

### Move

- Unsupported query parameter constants.
- Pagination and query-description constants.
- Query parameter pruning.
- Documented filter-alias discovery.
- Required endpoint-specific query definitions.
- Query parameter construction helpers.

Normalize an operation path once before matching it. Remove duplicate match arms such as both `v1/projects` and `projects` only when normalized input makes them equivalent and the export remains unchanged.

Do not build a parameter DSL or configuration object.

### Tests

Add no test. `ScrambleDocsTest` already verifies supported and unsupported query parameters.

### Verification

Run both documentation tests, export, and semantic comparison.

### Complete when

- Query correction behavior is contained in `PublicApiQueries`.
- All checks pass with no generated contract change.

## Phase 4 — Extract PublicApiResponses

This is the largest phase. Keep it as one cohesive class unless a concrete problem prevents extraction. Do not create separate response factories during this refactor.

### Files

- Create `app/Documentation/PublicApiResponses.php`.
- Modify `app/Providers/ScrambleServiceProvider.php`.
- Use `app/Documentation/PublicApiRouteCatalog.php`.

### Move

- Explicit `ApiError` reflection and application.
- Canonical shared error-response registration.
- Error response name and schema mapping.
- Inline and referenced response normalization.
- Shared `500` response application.
- Business-error descriptions and examples.
- Error-envelope schema builders.
- Response-code helpers.
- Validation error constants.

Inject `PublicApiRouteCatalog` and use `findForOperation()`. Continue reading definitions from `ErrorCode`.

Preserve this order:

1. Register shared components.
2. Normalize existing error responses.
3. Apply shared operation errors.
4. Apply explicit business errors last so their metadata is retained.

Do not change empty-object examples, response names, schema names, status mappings, or examples during this phase. Record those as separate contract corrections.

### Tests

Add no test. Existing tests verify canonical envelopes, status mappings, headers, business errors, and error registry behavior.

### Verification

Run both documentation tests, export, and semantic comparison.

### Complete when

- Error component and operation response behavior no longer live in the provider.
- `ErrorCode` remains the source of truth.
- All checks pass with no generated contract change.

## Phase 5 — Extract ScrambleCompatibility

### Files

- Create `app/Documentation/ScrambleCompatibility.php`.
- Modify `app/Providers/ScrambleServiceProvider.php`.
- Use `app/Documentation/PublicApiRouteCatalog.php`.

### Move

- `ensurePublicUpdateMethodParity()`.
- Relative server URL normalization currently inside `boot()`.
- `fixFeatureFlagsSchema()`.

Inject `PublicApiRouteCatalog` for PUT/PATCH route lookup.

Keep corrections explicitly named. Do not create a generic callable-transform list.

### Tests

Add no test:

- `RuntimeOpenApiParityTest` covers method parity.
- `ScrambleDocsTest` covers the server URL.
- The semantic before/after export comparison covers the feature-flags schema correction.

### Verification

Run both documentation tests, export, and semantic comparison.

### Complete when

- Scramble compatibility behavior no longer lives in the provider.
- All checks pass with no generated contract change.

## Phase 6 — Reduce provider to a composition root

### File

- Modify `app/Providers/ScrambleServiceProvider.php`.

Use automatic dependency injection:

```php
final class ScrambleServiceProvider extends ServiceProvider
{
    public function boot(
        PublicApiRouteCatalog $routes,
        PublicApiTags $tags,
        PublicApiQueries $queries,
        PublicApiResponses $responses,
        ScrambleCompatibility $compatibility,
    ): void {
        Scramble::routes(
            fn (Route $route): bool => $routes->isPublished($route),
        );

        Scramble::resolveTagsUsing(
            fn (RouteInfo $routeInfo): array => [$tags->resolve($routeInfo)],
        );

        Scramble::afterOpenApiGenerated(
            function (OpenApi $openApi) use (
                $tags,
                $queries,
                $responses,
                $compatibility,
            ): void {
                $compatibility->applyMethodCorrections($openApi);
                $tags->applyMetadata($openApi);
                $responses->apply($openApi);
                $queries->apply($openApi);
                $compatibility->normalizeServers($openApi);
                $compatibility->applySchemaCorrections($openApi);
            },
        );
    }
}
```

Adjust callback order only to preserve current output.

Remove:

- Empty `register()`.
- Unused imports.
- Historical commented-out code.
- Comments describing removed implementations.

Keep a short comment only where transformation order or a Scramble limitation is surprising.

### Tests

Add no test.

### Verification

Run both documentation tests, export, and semantic comparison.

### Complete when

- The provider contains registration and visible orchestration only.
- No extracted implementation helper remains in it.
- All checks pass with no generated contract change.

## Phase 7 — Final verification and cleanup

Do not add abstractions or broad new tests here.

1. Run the one new unit test and both documentation tests.
2. Run the full existing suite once.
3. Run static analysis and formatting checks.
4. Run Scramble analysis.
5. Export and compare with the Phase 0 baseline.
6. Confirm tracked `api.json` was not changed by the refactor.
7. Review imports, namespaces, names, and comments.

```powershell
php artisan test tests/Unit/Documentation/PublicApiRouteCatalogTest.php
php artisan test tests/Feature/Api/Contracts/Docs/ScrambleDocsTest.php
php artisan test tests/Feature/Api/Contracts/Docs/RuntimeOpenApiParityTest.php
composer test
composer stan
composer pint:test
php artisan scramble:analyze
php artisan scramble:export --path=storage/app/openapi-refactor-current.json
```

### Complete when

- All required checks pass.
- Before and current OpenAPI documents are semantically equal.
- Tracked `api.json` has no refactor-caused change.
- No unrelated working-tree change was overwritten or included.
- The provider reads as a short, explicit registration sequence.
- Future changes have one clear home: routes, tags, queries, responses, or compatibility.

## Rollback and commits

Use one refactor branch and one focused commit per successful extraction. Stage only phase files because the repository already has unrelated working-tree changes.

Suggested commits:

1. `refactor(openapi): extract public route catalog`
2. `refactor(openapi): extract public API tags`
3. `refactor(openapi): extract public query documentation`
4. `refactor(openapi): extract public response documentation`
5. `refactor(openapi): isolate Scramble compatibility fixes`
6. `refactor(openapi): simplify service provider composition`

If clean commits cannot be made without unrelated changes, keep work unstaged and report exact phase files. Do not reset, discard, or hide existing work.

## Deferred work

Handle separately after this refactor:

- OpenAPI request and response conformance testing.
- Published route changes.
- PUT/PATCH behavior changes.
- `{}` versus `[]` example corrections.
- Error schema, name, example, or status changes.
- Refactoring `PublicApiMiddlewareResponses`.
- A new endpoint-query documentation system.
- Replacing URI-based tag classification.
