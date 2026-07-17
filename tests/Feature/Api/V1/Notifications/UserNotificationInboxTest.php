<?php

declare(strict_types=1);

namespace Tests\Feature\Api\V1\Notifications;

use App\Enums\NotificationFilter;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;
use Tests\Traits\ProjectInvitationHelpers;
use Tests\Traits\ProjectSetup;

class UserNotificationInboxTest extends TestCase
{
    use ProjectInvitationHelpers;
    use ProjectSetup, RefreshDatabase;

    /** @test */
    public function auth_user_can_fetch_there_notifications(): void
    {
        $this->actingAsInvitedUser();

        $response = $this->withoutExceptionHandling()->getJson($this->apiV1Route('notifications.index'))
            ->assertOk()
            ->assertJsonStructure([
                'data',
                'meta' => [
                    'next_cursor',
                    'prev_cursor',
                    'per_page',
                ],
                'links' => [
                    'next',
                    'prev',
                ],
            ])
            ->assertJsonPath('data.0.type', 'ProjectInvitation')
            ->assertJsonPath('data.0.message', 'Sent you a project '.$this->project->name.' invitation')
            ->assertJsonPath('data.0.link', $this->apiV1Route('projects.show', ['project' => $this->project]))
            ->assertJsonPath('data.0.notifier.name', $this->user->name);

        $this->assertCount(1, $response->json('data'));
    }

    /** @test */
    public function auth_user_gets_paginated_empty_notifications_shape(): void
    {
        Sanctum::actingAs(User::factory()->create());

        $response = $this->getJson($this->apiV1Route('notifications.index'))
            ->assertOk()
            ->assertJsonStructure([
                'data',
                'meta' => [
                    'next_cursor',
                    'prev_cursor',
                    'per_page',
                ],
                'links' => [
                    'next',
                    'prev',
                ],
            ]);

        $this->assertSame([], $response->json('data'));
    }

    /** @test */
    public function auth_user_can_fetch_notifications_by_status(): void
    {
        $user = $this->actingAsInvitedUser();

        $unreadResponse = $this->getJson($this->notificationsUrl(['status' => NotificationFilter::UNREAD->value]));
        $this->assertCount(1, $unreadResponse->json('data'));

        $user->notifications()->latest()->first()->markAsRead();
        $readResponse = $this->getJson($this->notificationsUrl(['status' => NotificationFilter::READ->value]));
        $this->assertCount(1, $readResponse->json('data'));
    }

    /** @test */
    public function notification_index_rejects_legacy_top_level_status_alias(): void
    {
        $this->actingAsInvitedUser();

        $this->getJson($this->apiV1Route('notifications.index', query: [
            'status' => NotificationFilter::UNREAD->value,
        ]))
            ->assertUnprocessable()
            ->assertJsonValidationErrors('status');
    }

    /** @test */
    public function notification_index_rejects_legacy_string_filter_alias(): void
    {
        $this->actingAsInvitedUser();

        $this->getJson($this->apiV1Route('notifications.index', query: [
            'filter' => NotificationFilter::UNREAD->value,
        ]))
            ->assertUnprocessable()
            ->assertJsonValidationErrors('filter');
    }

    /** @test */
    public function notification_index_rejects_unsupported_nested_filter_keys(): void
    {
        $this->actingAsInvitedUser();

        $this->getJson($this->apiV1Route('notifications.index', query: [
            'filter' => ['state' => NotificationFilter::UNREAD->value],
        ]))
            ->assertUnprocessable()
            ->assertJsonValidationErrors('filter');
    }

    /** @test */
    public function notification_index_validates_filter_status(): void
    {
        $this->actingAsInvitedUser();

        $this->getJson($this->notificationsUrl(['status' => 'archived']))
            ->assertUnprocessable()
            ->assertJsonValidationErrors('filter.status');
    }

    /** @test */
    public function notification_index_rejects_unsupported_top_level_query_parameters(): void
    {
        $this->actingAsInvitedUser();

        $this->getJson($this->apiV1Route('notifications.index', query: [
            'sort' => 'password',
            'include' => 'passwords',
            'random' => 'value',
        ]))
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['sort', 'include', 'random']);
    }

    /** @test */
    public function auth_user_can_mark_all_notifications_as_read(): void
    {
        $user = User::factory()->create();

        // Create a read notification
        $this->sendInvitationToUser($this->project, $user);
        $this->addMember($this->project, $user);
        $this->postJson($this->apiV1ProjectRoute('tasks.store', $this->project), ['title' => 'new task added']);
        Sanctum::actingAs($user);

        $response = $this->withoutExceptionHandling()->patchJson($this->apiV1Route('notifications.markAllAsRead'));

        $response->assertStatus(200);
        $this->assertCount(0, $user->fresh()->unreadNotifications()->get());
    }

    /** @test */
    public function auth_user_can_delete_a_notification(): void
    {
        $user = $this->actingAsInvitedUser();

        $notification = $user->notifications()->latest()->first();

        $response = $this->deleteJson($this->apiV1Route('notifications.destroy', ['notification' => $notification->id]));

        $response->assertStatus(200);
        $response->assertJson(['message' => 'Notification deleted successfully.']);
        $this->assertCount(0, $user->fresh()->notifications);
    }

    /** @test */
    public function notification_index_supports_cursor_pagination(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        // Create a project for this user
        $userProject = $this->project->replicate();
        $userProject->user_id = $user->id;
        $userProject->save();

        // Add the user as an active member
        $userProject->members()->syncWithoutDetaching([
            $user->id => ['active' => true],
        ]);

        // Add another member who will create tasks (generating notifications for the user)
        $otherMember = User::factory()->create();
        $userProject->members()->syncWithoutDetaching([
            $otherMember->id => ['active' => true],
        ]);

        // Create 4 notifications for the user by having the other member create tasks
        Sanctum::actingAs($otherMember);
        $this->postJson($this->apiV1ProjectRoute('tasks.store', $userProject), ['title' => 'task 1']);
        $this->travelTo(now()->addSecond());
        $this->postJson($this->apiV1ProjectRoute('tasks.store', $userProject), ['title' => 'task 2']);
        $this->travelTo(now()->addSecond());
        $this->postJson($this->apiV1ProjectRoute('tasks.store', $userProject), ['title' => 'task 3']);
        $this->travelTo(now()->addSecond());
        $this->postJson($this->apiV1ProjectRoute('tasks.store', $userProject), ['title' => 'task 4']);
        $this->travelBack();

        // Switch back to the original user to check their notifications
        Sanctum::actingAs($user);
        // Fetch first page with per_page=2
        $firstPage = $this->getJson($this->apiV1Route('notifications.index', query: ['per_page' => 2]));

        $firstPage->assertOk()
            ->assertJsonCount(2, 'data')
            ->assertJsonPath('meta.next_cursor', fn ($cursor) => $cursor !== null);

        $nextCursor = $firstPage->json('meta.next_cursor');

        // Fetch second page using cursor
        $secondPage = $this->getJson($this->apiV1Route('notifications.index', query: ['per_page' => 2, 'cursor' => $nextCursor]));

        $secondPage->assertOk()
            ->assertJsonCount(2, 'data');
    }

    /** @test */
    public function auth_user_can_update_notification_status(): void
    {
        $user = $this->actingAsInvitedUser();

        $notification = $user->notifications()->latest()->first();

        // Update status to read
        $this->patchJson($this->apiV1Route('notifications.updateStatus', ['notification' => $notification->id]), ['status' => 'read']);
        $this->assertNotNull($notification->fresh()->read_at);

        // Update status to unread
        $this->patchJson($this->apiV1Route('notifications.updateStatus', ['notification' => $notification->id]), ['status' => 'unread']);
        $this->assertNull($notification->fresh()->read_at);
    }

    /** @test */
    public function notification_status_must_be_read_or_unread(): void
    {
        $user = $this->actingAsInvitedUser();

        $notification = $user->notifications()->latest()->first();

        $this->patchJson($this->apiV1Route('notifications.updateStatus', ['notification' => $notification->id]), ['status' => 'archived'])
            ->assertStatus(422)
            ->assertJsonValidationErrors('status');
    }

    protected function actingAsInvitedUser(): User
    {
        $user = User::factory()->create();
        $this->sendInvitationToUser($this->project, $user);
        Sanctum::actingAs($user);

        return $user;
    }

    /**
     * @param  array<string, mixed>  $filters
     */
    private function notificationsUrl(array $filters = []): string
    {
        return $this->apiV1Route('notifications.index', query: $filters === [] ? [] : ['filter' => $filters]);
    }
}
