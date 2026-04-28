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

        $response = $this->withoutExceptionHandling()->getJson('/api/v1/notifications');

        $this->assertCount(1, $response->json('data'));
    }

    /** @test */
    public function auth_user_can_fetch_notifications_by_status(): void
    {
        $user = $this->actingAsInvitedUser();

        $unreadResponse = $this->getJson('/api/v1/notifications?filter='.NotificationFilter::UNREAD->value);
        $this->assertCount(1, $unreadResponse->json('data'));

        $user->notifications()->latest()->first()->markAsRead();
        $readResponse = $this->getJson('/api/v1/notifications?filter='.NotificationFilter::READ->value);
        $this->assertCount(1, $readResponse->json('data'));
    }

    /** @test */
    public function auth_user_can_mark_all_notifications_as_read(): void
    {
        $user = User::factory()->create();

        // Create a read notification
        $this->sendInvitationToUser($this->project, $user);
        $this->addMember($this->project, $user);
        $this->postJson($this->project->path().'/tasks', ['title' => 'new task added']);
        Sanctum::actingAs($user);

        $response = $this->withoutExceptionHandling()->patchJson('/api/v1/notifications/read');

        $response->assertStatus(200);
        $this->assertCount(0, $user->fresh()->unreadNotifications()->get());
    }

    /** @test */
    public function auth_user_can_delete_a_notification(): void
    {
        $user = $this->actingAsInvitedUser();

        $notification = $user->notifications()->latest()->first();

        $response = $this->deleteJson('/api/v1/notifications/'.$notification->id);

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
        $this->patchJson("/api/v1/notifications/{$notification->id}/status", ['status' => 'read']);
        $this->assertNotNull($notification->fresh()->read_at);

        // Update status to unread
        $this->patchJson("/api/v1/notifications/{$notification->id}/status", ['status' => 'unread']);
        $this->assertNull($notification->fresh()->read_at);
    }

    public function projectUpdate($project, $user): void
    {
        $this->patchJson($project->path(), ['notes' => 'Project notes updated.']);
    }

    protected function sendInvitationToUser($project, $user)
    {
        $this->postJson($this->project->path().'/invitations', [
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
}
