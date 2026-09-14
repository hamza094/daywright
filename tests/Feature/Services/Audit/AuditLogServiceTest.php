<?php

declare(strict_types=1);

namespace Tests\Feature\Services\Audit;

use App\Logging\ScrubSensitiveData;
use App\Models\AuditLog;
use App\Models\User;
use App\Services\Audit\AuditLogService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

final class AuditLogServiceTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function sanitizes_old_values_before_persistence(): void
    {
        $sanitizer = new ScrubSensitiveData;
        $service = new AuditLogService($sanitizer);

        $user = User::factory()->create();

        $oldValues = [
            'email' => 'old@example.com',
            'access_token' => 'secret-access-token',
            'password' => 'old-password',
        ];

        $service->log(
            'user.profile.updated',
            $user,
            $oldValues,
            ['email' => 'new@example.com'],
            ['request_id' => 'req-123'],
        );

        $auditLog = AuditLog::latest()->first();

        $this->assertNotNull($auditLog);
        $this->assertArrayHasKey('email', $auditLog->old_values);
        $this->assertSame('old@example.com', $auditLog->old_values['email']);
        $this->assertArrayHasKey('access_token', $auditLog->old_values);
        $this->assertSame(ScrubSensitiveData::REDACTED, $auditLog->old_values['access_token']);
        $this->assertArrayHasKey('password', $auditLog->old_values);
        $this->assertSame(ScrubSensitiveData::REDACTED, $auditLog->old_values['password']);
    }

    #[Test]
    public function sanitizes_new_values_before_persistence(): void
    {
        $sanitizer = new ScrubSensitiveData;
        $service = new AuditLogService($sanitizer);

        $user = User::factory()->create();

        $newValues = [
            'email' => 'new@example.com',
            'refresh_token' => 'secret-refresh-token',
            'api_key' => 'secret-api-key',
        ];

        $service->log(
            'user.profile.updated',
            $user,
            ['email' => 'old@example.com'],
            $newValues,
            ['request_id' => 'req-456'],
        );

        $auditLog = AuditLog::latest()->first();

        $this->assertNotNull($auditLog);
        $this->assertArrayHasKey('email', $auditLog->new_values);
        $this->assertSame('new@example.com', $auditLog->new_values['email']);
        $this->assertArrayHasKey('refresh_token', $auditLog->new_values);
        $this->assertSame(ScrubSensitiveData::REDACTED, $auditLog->new_values['refresh_token']);
        $this->assertArrayHasKey('api_key', $auditLog->new_values);
        $this->assertSame(ScrubSensitiveData::REDACTED, $auditLog->new_values['api_key']);
    }

    #[Test]
    public function sanitizes_metadata_before_persistence(): void
    {
        $sanitizer = new ScrubSensitiveData;
        $service = new AuditLogService($sanitizer);

        $user = User::factory()->create();

        $metadata = [
            'request_id' => 'req-789',
            'headers' => [
                'Authorization' => 'Bearer secret-auth-token',
            ],
            'provider' => [
                'client_secret' => 'secret-client-secret',
            ],
        ];

        $service->log(
            'user.login',
            $user,
            null,
            null,
            $metadata,
        );

        $auditLog = AuditLog::latest()->first();

        $this->assertNotNull($auditLog);
        $this->assertArrayHasKey('request_id', $auditLog->metadata);
        $this->assertSame('req-789', $auditLog->metadata['request_id']);
        $this->assertArrayHasKey('headers', $auditLog->metadata);
        $this->assertArrayHasKey('Authorization', $auditLog->metadata['headers']);
        $this->assertSame(ScrubSensitiveData::REDACTED, $auditLog->metadata['headers']['Authorization']);
        $this->assertArrayHasKey('provider', $auditLog->metadata);
        $this->assertArrayHasKey('client_secret', $auditLog->metadata['provider']);
        $this->assertSame(ScrubSensitiveData::REDACTED, $auditLog->metadata['provider']['client_secret']);
    }

    #[Test]
    public function preserves_safe_identifiers_in_audit_record(): void
    {
        $sanitizer = new ScrubSensitiveData;
        $service = new AuditLogService($sanitizer);

        $user = User::factory()->create();

        $metadata = [
            'request_id' => 'safe-request-123',
            'event_id' => 'safe-event-456',
            'subscription_id' => 'sub-789',
            'user_id' => $user->id,
            'operation' => 'user.update',
        ];

        $service->log(
            'user.profile.updated',
            $user,
            ['email' => 'old@example.com'],
            ['email' => 'new@example.com'],
            $metadata,
        );

        $auditLog = AuditLog::latest()->first();

        $this->assertNotNull($auditLog);
        $this->assertSame('safe-request-123', $auditLog->metadata['request_id']);
        $this->assertSame('safe-event-456', $auditLog->metadata['event_id']);
        $this->assertSame('sub-789', $auditLog->metadata['subscription_id']);
        $this->assertSame($user->id, $auditLog->metadata['user_id']);
        $this->assertSame('user.update', $auditLog->metadata['operation']);
    }

    #[Test]
    public function sanitizes_nested_secrets_in_all_audit_fields(): void
    {
        $sanitizer = new ScrubSensitiveData;
        $service = new AuditLogService($sanitizer);

        $user = User::factory()->create();

        $oldValues = [
            'nested' => [
                'deep' => [
                    'access_token' => 'deep-secret-token',
                ],
            ],
        ];

        $newValues = [
            'provider' => [
                'refresh_token' => 'nested-refresh-token',
            ],
        ];

        $metadata = [
            'context' => [
                'authorization' => 'nested-auth-token',
            ],
        ];

        $service->log(
            'user.api.updated',
            $user,
            $oldValues,
            $newValues,
            $metadata,
        );

        $auditLog = AuditLog::latest()->first();

        $this->assertNotNull($auditLog);
        $this->assertSame(ScrubSensitiveData::REDACTED, $auditLog->old_values['nested']['deep']['access_token']);
        $this->assertSame(ScrubSensitiveData::REDACTED, $auditLog->new_values['provider']['refresh_token']);
        $this->assertSame(ScrubSensitiveData::REDACTED, $auditLog->metadata['context']['authorization']);
    }

    #[Test]
    public function handles_null_old_and_new_values(): void
    {
        $sanitizer = new ScrubSensitiveData;
        $service = new AuditLogService($sanitizer);

        $user = User::factory()->create();

        $service->log(
            'user.deleted',
            $user,
            null,
            null,
            ['request_id' => 'req-null'],
        );

        $auditLog = AuditLog::latest()->first();

        $this->assertNotNull($auditLog);
        $this->assertNull($auditLog->old_values);
        $this->assertNull($auditLog->new_values);
        $this->assertSame('req-null', $auditLog->metadata['request_id']);
    }

    #[Test]
    public function preserves_event_name_and_auditable_information(): void
    {
        $sanitizer = new ScrubSensitiveData;
        $service = new AuditLogService($sanitizer);

        $user = User::factory()->create();

        $service->log(
            'user.profile.updated',
            $user,
            ['email' => 'old@example.com'],
            ['email' => 'new@example.com'],
            ['request_id' => 'req-actor'],
        );

        $auditLog = AuditLog::latest()->first();

        $this->assertNotNull($auditLog);
        $this->assertSame('user.profile.updated', $auditLog->event);
        $this->assertSame(User::class, $auditLog->auditable_type);
        $this->assertSame($user->id, $auditLog->auditable_id);
    }
}
