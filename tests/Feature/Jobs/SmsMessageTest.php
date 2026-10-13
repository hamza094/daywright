<?php

declare(strict_types=1);

namespace Tests\Feature\Jobs;

use App\Actions\Project\DispatchProjectMessageAction;
use App\Exceptions\Integrations\Sms\SmsMissingRecipientException;
use App\Exceptions\Integrations\Sms\SmsProviderException;
use App\Jobs\SmsMessage;
use App\Models\Message;
use App\Models\Project;
use App\Models\User;
use App\Models\UserInfo;
use App\Services\VonageSmsService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

final class SmsMessageTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    private Project $project;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create();
        UserInfo::factory()->for($this->user)->create(['mobile' => '+15551234567']);
        $this->project = Project::factory()->for($this->user)->create();
    }

    #[Test]
    public function it_succeeds_when_provider_returns_success(): void
    {
        $message = Message::factory()->for($this->project)->create([
            'type' => 'sms',
            'delivered' => false,
        ]);
        $message->users()->attach($this->user);

        $smsService = Mockery::mock(VonageSmsService::class);
        $smsService->shouldReceive('send')
            ->once()
            ->with(
                Mockery::on(fn (Project $p): bool => $p->id === $this->project->id),
                Mockery::on(fn (Message $m): bool => $m->id === $message->id)
            )
            ->andReturn("The message was sent successfully\n");

        $job = new SmsMessage($this->project->id, $message->id);
        $job->handle($smsService);

        // Does not throw
        $this->assertTrue(true);
    }

    #[Test]
    public function it_throws_and_fails_job_when_recipient_is_missing(): void
    {
        $userWithoutMobile = User::factory()->create();
        UserInfo::factory()->for($userWithoutMobile)->create(['mobile' => null]);
        $message = Message::factory()->for($this->project)->create([
            'type' => 'sms',
            'delivered' => false,
        ]);
        $message->users()->attach($userWithoutMobile);

        $smsService = Mockery::mock(VonageSmsService::class);
        $smsService->shouldReceive('send')
            ->once()
            ->andThrow(new SmsMissingRecipientException('No mobile recipient available.'));

        $job = new SmsMessage($this->project->id, $message->id);

        $this->expectException(SmsMissingRecipientException::class);
        $job->handle($smsService);
    }

    #[Test]
    public function it_throws_permanent_exception_when_provider_status_is_non_zero_rejection(): void
    {
        $message = Message::factory()->for($this->project)->create([
            'type' => 'sms',
            'delivered' => false,
        ]);
        $message->users()->attach($this->user);

        $smsService = Mockery::mock(VonageSmsService::class);
        $smsService->shouldReceive('send')
            ->once()
            ->andThrow(SmsProviderException::fromStatus(7)); // Number barred (permanent)

        $job = new SmsMessage($this->project->id, $message->id);

        $this->expectException(SmsProviderException::class);
        $this->expectExceptionMessage('Number barred');

        $job->handle($smsService);
    }

    #[Test]
    public function it_identifies_transient_provider_status_codes_for_retry(): void
    {
        $throttled = SmsProviderException::fromStatus(1);
        $this->assertTrue($throttled->isTransient());
        $this->assertSame(1, $throttled->providerStatus());

        $internalError = SmsProviderException::fromStatus(5);
        $this->assertTrue($internalError->isTransient());

        $commError = SmsProviderException::fromStatus(13);
        $this->assertTrue($commError->isTransient());

        $barred = SmsProviderException::fromStatus(7);
        $this->assertFalse($barred->isTransient());

        $invalidCreds = SmsProviderException::fromStatus(3);
        $this->assertFalse($invalidCreds->isTransient());
    }

    #[Test]
    public function failed_sms_in_dispatch_batch_does_not_set_message_delivered(): void
    {
        $message = Message::factory()->for($this->project)->create([
            'type' => 'sms',
            'delivered' => false,
            'batch_id' => null,
        ]);
        $message->users()->attach($this->user);

        // Bind mock service that throws permanent rejection
        $mockService = Mockery::mock(VonageSmsService::class);
        $mockService->shouldReceive('send')
            ->andThrow(SmsProviderException::fromStatus(7));
        $this->app->instance(VonageSmsService::class, $mockService);

        $action = new DispatchProjectMessageAction;

        try {
            $action->execute($this->project, $message);
        } catch (SmsProviderException) {
            // Expected to throw in sync queue driver
        }

        $message->refresh();
        // Crucial assertion: failed SMS batch MUST NEVER set delivered to true!
        $this->assertFalse((bool) $message->delivered);
    }
}
