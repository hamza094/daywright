<?php

declare(strict_types=1);

namespace Tests\Unit\Logging;

use App\Logging\ScrubSensitiveData;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

final class ScrubSensitiveDataTest extends TestCase
{
    #[Test]
    public function access_token_is_redacted(): void
    {
        $sanitizer = new ScrubSensitiveData;
        $data = ['access_token' => 'secret-token-123'];

        $result = $sanitizer->sanitize($data);

        $this->assertSame(ScrubSensitiveData::REDACTED, $result['access_token']);
    }

    #[Test]
    public function refresh_token_is_redacted(): void
    {
        $sanitizer = new ScrubSensitiveData;
        $data = ['refresh_token' => 'secret-refresh-456'];

        $result = $sanitizer->sanitize($data);

        $this->assertSame(ScrubSensitiveData::REDACTED, $result['refresh_token']);
    }

    #[Test]
    public function authorization_is_redacted_regardless_of_capitalization(): void
    {
        $sanitizer = new ScrubSensitiveData;

        $data1 = ['Authorization' => 'Bearer secret-auth'];
        $result1 = $sanitizer->sanitize($data1);
        $this->assertSame(ScrubSensitiveData::REDACTED, $result1['Authorization']);

        $data2 = ['authorization' => 'Bearer secret-auth'];
        $result2 = $sanitizer->sanitize($data2);
        $this->assertSame(ScrubSensitiveData::REDACTED, $result2['authorization']);

        $data3 = ['AUTHORIZATION' => 'Bearer secret-auth'];
        $result3 = $sanitizer->sanitize($data3);
        $this->assertSame(ScrubSensitiveData::REDACTED, $result3['AUTHORIZATION']);
    }

    #[Test]
    public function x_api_key_is_redacted_after_normalization(): void
    {
        $sanitizer = new ScrubSensitiveData;

        $data1 = ['X-API-Key' => 'secret-api-key'];
        $result1 = $sanitizer->sanitize($data1);
        $this->assertSame(ScrubSensitiveData::REDACTED, $result1['X-API-Key']);

        $data2 = ['x_api_key' => 'secret-api-key'];
        $result2 = $sanitizer->sanitize($data2);
        $this->assertSame(ScrubSensitiveData::REDACTED, $result2['x_api_key']);
    }

    #[Test]
    public function sensitive_values_in_nested_arrays_are_redacted(): void
    {
        $sanitizer = new ScrubSensitiveData;
        $data = [
            'provider' => [
                'access_token' => 'nested-secret-token',
                'refresh_token' => 'nested-refresh-token',
            ],
            'headers' => [
                'Authorization' => 'Bearer nested-auth',
            ],
        ];

        $result = $sanitizer->sanitize($data);

        $this->assertSame(ScrubSensitiveData::REDACTED, $result['provider']['access_token']);
        $this->assertSame(ScrubSensitiveData::REDACTED, $result['provider']['refresh_token']);
        $this->assertSame(ScrubSensitiveData::REDACTED, $result['headers']['Authorization']);
    }

    #[Test]
    public function request_id_remains_unchanged(): void
    {
        $sanitizer = new ScrubSensitiveData;
        $data = ['request_id' => 'request-visible-123'];

        $result = $sanitizer->sanitize($data);

        $this->assertSame('request-visible-123', $result['request_id']);
    }

    #[Test]
    public function event_id_remains_unchanged(): void
    {
        $sanitizer = new ScrubSensitiveData;
        $data = ['event_id' => 'event-visible-456'];

        $result = $sanitizer->sanitize($data);

        $this->assertSame('event-visible-456', $result['event_id']);
    }

    #[Test]
    public function user_id_remains_unchanged(): void
    {
        $sanitizer = new ScrubSensitiveData;
        $data = ['user_id' => 123];

        $result = $sanitizer->sanitize($data);

        $this->assertSame(123, $result['user_id']);
    }

    #[Test]
    public function status_remains_unchanged(): void
    {
        $sanitizer = new ScrubSensitiveData;
        $data = ['status' => 'active'];

        $result = $sanitizer->sanitize($data);

        $this->assertSame('active', $result['status']);
    }

    #[Test]
    public function operation_remains_unchanged(): void
    {
        $sanitizer = new ScrubSensitiveData;
        $data = ['operation' => 'user.login'];

        $result = $sanitizer->sanitize($data);

        $this->assertSame('user.login', $result['operation']);
    }

    #[Test]
    public function fields_ending_with_token_are_redacted(): void
    {
        $sanitizer = new ScrubSensitiveData;

        $data = [
            'reset_token' => 'reset-secret',
            'verification_token' => 'verify-secret',
            'api_token' => 'api-secret',
        ];

        $result = $sanitizer->sanitize($data);

        $this->assertSame(ScrubSensitiveData::REDACTED, $result['reset_token']);
        $this->assertSame(ScrubSensitiveData::REDACTED, $result['verification_token']);
        $this->assertSame(ScrubSensitiveData::REDACTED, $result['api_token']);
    }

    #[Test]
    public function mixed_sensitive_and_safe_data(): void
    {
        $sanitizer = new ScrubSensitiveData;
        $data = [
            'request_id' => 'req-123',
            'access_token' => 'secret',
            'user_id' => 456,
            'authorization' => 'Bearer token',
            'operation' => 'create',
        ];

        $result = $sanitizer->sanitize($data);

        $this->assertSame('req-123', $result['request_id']);
        $this->assertSame(ScrubSensitiveData::REDACTED, $result['access_token']);
        $this->assertSame(456, $result['user_id']);
        $this->assertSame(ScrubSensitiveData::REDACTED, $result['authorization']);
        $this->assertSame('create', $result['operation']);
    }

    #[Test]
    public function array_valued_authorization_header_is_fully_redacted(): void
    {
        $sanitizer = new ScrubSensitiveData;
        $data = [
            'Authorization' => ['Bearer array-secret'],
        ];

        $result = $sanitizer->sanitize($data);

        $this->assertSame(ScrubSensitiveData::REDACTED, $result['Authorization']);
    }

    #[Test]
    public function daywright_specific_keys_are_redacted(): void
    {
        $sanitizer = new ScrubSensitiveData;
        $data = [
            'current_password' => 'password-secret',
            'secret_key' => 'provider-secret',
            'webhook_secret' => 'webhook-secret',
            'vendor_auth_code' => 'paddle-secret',
            'code_verifier' => 'oauth-secret',
            'recovery_codes' => ['code-one', 'code-two'],
        ];

        $result = $sanitizer->sanitize($data);

        $this->assertSame(ScrubSensitiveData::REDACTED, $result['current_password']);
        $this->assertSame(ScrubSensitiveData::REDACTED, $result['secret_key']);
        $this->assertSame(ScrubSensitiveData::REDACTED, $result['webhook_secret']);
        $this->assertSame(ScrubSensitiveData::REDACTED, $result['vendor_auth_code']);
        $this->assertSame(ScrubSensitiveData::REDACTED, $result['code_verifier']);
        $this->assertSame(ScrubSensitiveData::REDACTED, $result['recovery_codes']);
    }

    #[Test]
    public function password_containing_keys_are_redacted(): void
    {
        $sanitizer = new ScrubSensitiveData;
        $data = [
            'new_password' => 'new-secret',
            'old_password' => 'old-secret',
            'confirm_password' => 'confirm-secret',
        ];

        $result = $sanitizer->sanitize($data);

        $this->assertSame(ScrubSensitiveData::REDACTED, $result['new_password']);
        $this->assertSame(ScrubSensitiveData::REDACTED, $result['old_password']);
        $this->assertSame(ScrubSensitiveData::REDACTED, $result['confirm_password']);
    }

    #[Test]
    public function secret_suffix_keys_are_redacted(): void
    {
        $sanitizer = new ScrubSensitiveData;
        $data = [
            'api_secret' => 'api-secret-value',
            'refresh_secret' => 'refresh-secret-value',
        ];

        $result = $sanitizer->sanitize($data);

        $this->assertSame(ScrubSensitiveData::REDACTED, $result['api_secret']);
        $this->assertSame(ScrubSensitiveData::REDACTED, $result['refresh_secret']);
    }

    #[Test]
    public function secret_key_suffix_keys_are_redacted(): void
    {
        $sanitizer = new ScrubSensitiveData;
        $data = [
            'provider_secret_key' => 'provider-secret-value',
        ];

        $result = $sanitizer->sanitize($data);

        $this->assertSame(ScrubSensitiveData::REDACTED, $result['provider_secret_key']);
    }

    #[Test]
    public function auth_code_suffix_keys_are_redacted(): void
    {
        $sanitizer = new ScrubSensitiveData;
        $data = [
            'oauth_auth_code' => 'oauth-auth-value',
        ];

        $result = $sanitizer->sanitize($data);

        $this->assertSame(ScrubSensitiveData::REDACTED, $result['oauth_auth_code']);
    }
}
