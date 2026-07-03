<?php

declare(strict_types=1);

namespace Tests\Unit\Listeners;

use App\Events\PasswordUpdateEvent;
use App\Listeners\SendPasswordUpdateEmail;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class SendPasswordUpdateEmailTest extends TestCase
{
    use RefreshDatabase;

    /** @test */
    public function listener_implements_should_queue(): void
    {
        $listener = new SendPasswordUpdateEmail;

        $this->assertInstanceOf(\Illuminate\Contracts\Queue\ShouldQueue::class, $listener);
    }

    /** @test */
    public function listener_sends_password_update_email(): void
    {
        Mail::fake();

        $user = User::factory()->create();
        $listener = new SendPasswordUpdateEmail;
        $event = new PasswordUpdateEvent($user, now()->toIso8601String());

        $listener->handle($event);

        Mail::assertQueued(\App\Mail\PasswordUpdate::class, fn ($mail) => $mail->hasTo($user->email));
    }
}
