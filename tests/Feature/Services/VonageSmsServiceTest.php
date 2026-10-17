<?php

declare(strict_types=1);

namespace Tests\Feature\Services;

use App\Exceptions\Integrations\Sms\SmsMissingRecipientException;
use App\Exceptions\Integrations\Sms\SmsProviderException;
use App\Models\Message;
use App\Models\Project;
use App\Models\User;
use App\Models\UserInfo;
use App\Services\VonageSmsService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery;
use PHPUnit\Framework\Attributes\Test;
use RuntimeException;
use Tests\TestCase;
use Vonage\Client;
use Vonage\SMS\Client as SmsClient;
use Vonage\SMS\Collection;
use Vonage\SMS\SentSMS;

final class VonageSmsServiceTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    private Project $project;

    protected function setUp(): void
    {
        parent::setUp();

        config(['services.vonage.from' => 'Daywright']);

        $this->user = User::factory()->create();
        UserInfo::factory()->for($this->user)->create(['mobile' => '+15551234567']);
        $this->project = Project::factory()->for($this->user)->create();
    }

    #[Test]
    public function it_throws_when_message_has_no_recipient_with_mobile_number(): void
    {
        $userWithoutMobile = User::factory()->create();
        UserInfo::factory()->for($userWithoutMobile)->create(['mobile' => null]);
        $message = Message::factory()->for($this->project)->create([
            'type' => 'sms',
        ]);
        $message->users()->attach($userWithoutMobile);

        $service = new VonageSmsService;

        $this->expectException(SmsMissingRecipientException::class);
        $this->expectExceptionMessage("No mobile recipient available for message [{$message->id}].");

        $service->send($this->project, $message);
    }

    #[Test]
    public function it_sends_sms_successfully_when_vonage_status_is_zero(): void
    {
        $message = Message::factory()->for($this->project)->create([
            'type' => 'sms',
            'message' => 'Hello team',
        ]);
        $message->users()->attach($this->user);

        $sentSms = Mockery::mock(SentSMS::class);
        $sentSms->shouldReceive('getStatus')->andReturn(0);

        $collection = Mockery::mock(Collection::class);
        $collection->shouldReceive('current')->andReturn($sentSms);

        $smsClient = Mockery::mock(SmsClient::class);
        $smsClient->shouldReceive('send')->once()->andReturn($collection);

        $vonageClient = Mockery::mock(Client::class);
        $vonageClient->shouldReceive('sms')->andReturn($smsClient);

        $service = new VonageSmsService($vonageClient);

        $result = $service->send($this->project, $message);

        $this->assertSame("The message was sent successfully\n", $result);
    }

    #[Test]
    public function it_throws_typed_provider_exception_when_vonage_returns_non_zero_status(): void
    {
        $message = Message::factory()->for($this->project)->create([
            'type' => 'sms',
            'message' => 'Hello team',
        ]);
        $message->users()->attach($this->user);

        $sentSms = Mockery::mock(SentSMS::class);
        $sentSms->shouldReceive('getStatus')->andReturn(7); // Number barred

        $collection = Mockery::mock(Collection::class);
        $collection->shouldReceive('current')->andReturn($sentSms);

        $smsClient = Mockery::mock(SmsClient::class);
        $smsClient->shouldReceive('send')->once()->andReturn($collection);

        $vonageClient = Mockery::mock(Client::class);
        $vonageClient->shouldReceive('sms')->andReturn($smsClient);

        $service = new VonageSmsService($vonageClient);

        try {
            $service->send($this->project, $message);
            $this->fail('Expected SmsProviderException was not thrown');
        } catch (SmsProviderException $e) {
            $this->assertSame(7, $e->providerStatus());
            $this->assertFalse($e->isTransient());
            $this->assertStringContainsKeyOrSub('Number barred', $e->getMessage());
        }
    }

    #[Test]
    public function it_wraps_provider_connection_exceptions_as_transient(): void
    {
        $message = Message::factory()->for($this->project)->create([
            'type' => 'sms',
            'message' => 'Hello team',
        ]);
        $message->users()->attach($this->user);

        $smsClient = Mockery::mock(SmsClient::class);
        $smsClient->shouldReceive('send')->once()->andThrow(new RuntimeException('Connection timed out'));

        $vonageClient = Mockery::mock(Client::class);
        $vonageClient->shouldReceive('sms')->andReturn($smsClient);

        $service = new VonageSmsService($vonageClient);

        try {
            $service->send($this->project, $message);
            $this->fail('Expected SmsProviderException was not thrown');
        } catch (SmsProviderException $e) {
            $this->assertTrue($e->isTransient());
            $this->assertStringContainsKeyOrSub('Connection timed out', $e->getMessage());
        }
    }

    private function assertStringContainsKeyOrSub(string $needle, string $haystack): void
    {
        $this->assertStringContainsString($needle, $haystack);
    }
}
