<?php

declare(strict_types=1);

namespace App\Documentation;

use Dedoc\Scramble\Support\Generator\OpenApi;
use Dedoc\Scramble\Support\Generator\Operation;
use Dedoc\Scramble\Support\Generator\Tag;
use Dedoc\Scramble\Support\RouteInfo;
use Illuminate\Support\Str;

final class PublicApiTags
{
    public function resolve(RouteInfo $routeInfo): string
    {
        $uri = $routeInfo->route->uri;

        return match (true) {
            Str::startsWith($uri, [
                'api/v1/register',
                'api/v1/login',
                'api/v1/logout',
                'api/v1/forgot-password',
                'api/v1/reset-password',
                'api/v1/email/',
                'api/v1/session/',
                'api/v1/auth/',
                'api/v1/twofactor/',
            ]) => 'Authentication',
            $uri === 'api/v1/scopes' => 'API Tokens',
            Str::startsWith($uri, 'api/v1/api-tokens') => 'API Tokens',
            Str::startsWith($uri, 'api/v1/users/me/subscription') => 'Subscription',
            Str::startsWith($uri, 'api/v1/dashboard/') => 'Dashboard',
            Str::startsWith($uri, 'api/v1/notifications') => 'Notifications',
            $uri === 'api/v1/stages' => 'Stages',
            Str::contains($uri, '/conversations') => 'Conversations',
            Str::contains($uri, '/tasks') || $uri === 'api/v1/task-statuses' => 'Tasks',
            Str::contains($uri, '/invitations') || Str::contains($uri, '/members/') || $uri === 'api/v1/users/me/invitations' => 'Invitations',
            Str::startsWith($uri, 'api/v1/users') => 'Users',
            Str::startsWith($uri, 'api/v1/projects') => 'Projects',
            default => 'Other',
        };
    }

    public function applyMetadata(OpenApi $openApi): void
    {
        $usedTags = collect($openApi->paths)
            ->flatMap(static fn ($path): array => array_values($path->operations))
            ->flatMap(static fn (Operation $operation): array => $operation->tags)
            ->unique()
            ->values();

        $openApi->tags = collect($this->getTagDefinitions())
            ->filter(static fn (array $metadata, string $tag): bool => $usedTags->contains($tag))
            ->map(static function (array $metadata, string $tag): Tag {
                $tagDefinition = new Tag($tag, $metadata['description']);
                $tagDefinition->setAttribute('weight', $metadata['weight']);

                return $tagDefinition;
            })
            ->sortBy(static fn (Tag $tag): int => (int) $tag->getAttribute('weight', PHP_INT_MAX))
            ->values()
            ->all();
    }

    /**
     * @return array<string, array{description: string, weight: int}>
     */
    private function getTagDefinitions(): array
    {
        return [
            'Authentication' => [
                'description' => 'Token, session, OAuth, password reset, email verification, and two-factor authentication endpoints.',
                'weight' => 10,
            ],
            'Users' => [
                'description' => 'Current-user, profile, avatar, and public user account management endpoints.',
                'weight' => 20,
            ],
            'Invitations' => [
                'description' => 'Personal and project invitation management endpoints.',
                'weight' => 30,
            ],
            'API Tokens' => [
                'description' => 'Personal access token management endpoints for bearer-token clients.',
                'weight' => 40,
            ],
            'Subscription' => [
                'description' => 'Subscription checkout, plan swap, cancellation, and subscription status endpoints.',
                'weight' => 50,
            ],
            'Dashboard' => [
                'description' => 'Released dashboard read models for charts, insights, tasks, activities, and projects.',
                'weight' => 60,
            ],
            'Notifications' => [
                'description' => 'Notification listing, bulk-read, status update, and deletion endpoints.',
                'weight' => 70,
            ],
            'Projects' => [
                'description' => 'Released public project CRUD, insights, limits, and activity endpoints.',
                'weight' => 80,
            ],
            'Stages' => [
                'description' => 'Shared project stage listing endpoints.',
                'weight' => 90,
            ],
            'Tasks' => [
                'description' => 'Released task CRUD, assignment, archive, restore, and task status endpoints.',
                'weight' => 100,
            ],
            'Conversations' => [
                'description' => 'Released project conversation list, create, attachment upload, and delete endpoints.',
                'weight' => 110,
            ],
        ];
    }
}
