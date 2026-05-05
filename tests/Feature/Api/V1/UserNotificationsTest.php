<?php

declare(strict_types=1);

namespace Tests\Feature\Api\V1;

use App\Enums\NotificationFilter;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;
use Tests\Traits\ProjectSetup;

class UserNotificationsTest extends TestCase
{
    use ProjectSetup, RefreshDatabase;

    /** @test */
    public function auth_user_can_fetch_there_notifications(): void
    {
        $this->actingAsInvitedUser();

        $response = $this->withoutExceptionHandling()->getJson($this->apiV1Route('notifications.index'))
            ->assertOk()
            ->assertJsonStructure([
                'data',
                'meta',
                'links',
            ]);

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
                'meta',
                'links',
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
    public function notification_index_validates_filter_status(): void
    {
        $this->actingAsInvitedUser();

        $this->getJson($this->notificationsUrl(['status' => 'archived']))
            ->assertUnprocessable()
            ->assertJsonValidationErrors('filter.status');
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

    public function projectUpdate($project, $user): void
    {
        $this->patchJson($this->apiV1ProjectRoute('projects.update', $project), ['notes' => 'Project notes updated.']);
    }

    protected function sendInvitationToUser($project, $user)
    {
        $this->postJson($this->apiV1ProjectRoute('send.invitation', $project), [
            'email' => $user->email,
        ]);
    }

    protected function actingAsInvitedUser(): User
    {
        $user = User::factory()->create();
        $this->sendInvitationToUser($this->project, $user);
        Sanctum::actingAs($user);

        return $user;
    }

    protected function addMember($project, $user)
    {
        $this->project
            ->members()
            ->attach($user, ['active' => true]);
    }

    /**
     * @param  array<string, mixed>  $filters
     */
    private function notificationsUrl(array $filters = []): string
    {
        return $this->apiV1Route('notifications.index', query: $filters === [] ? [] : ['filter' => $filters]);
    }
}
