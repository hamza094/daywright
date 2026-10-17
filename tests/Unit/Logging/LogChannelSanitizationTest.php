<?php

declare(strict_types=1);

namespace Tests\Unit\Logging;

use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Log;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

final class LogChannelSanitizationTest extends TestCase
{
    #[Test]
    public function daily_json_channel_has_scrub_sensitive_data_tap(): void
    {
        $config = Config::get('logging.channels.daily_json');

        $this->assertIsArray($config);
        $this->assertArrayHasKey('tap', $config);
        $this->assertContains(\App\Logging\ScrubSensitiveData::class, $config['tap']);
    }

    #[Test]
    public function webhook_channel_has_scrub_sensitive_data_tap(): void
    {
        $config = Config::get('logging.channels.webhook');

        $this->assertIsArray($config);
        $this->assertArrayHasKey('tap', $config);
        $this->assertContains(\App\Logging\ScrubSensitiveData::class, $config['tap']);
    }

    #[Test]
    public function zoom_channel_has_scrub_sensitive_data_tap(): void
    {
        $config = Config::get('logging.channels.zoom');

        $this->assertIsArray($config);
        $this->assertArrayHasKey('tap', $config);
        $this->assertContains(\App\Logging\ScrubSensitiveData::class, $config['tap']);
    }

    #[Test]
    public function papertrail_channel_has_scrub_sensitive_data_tap(): void
    {
        $config = Config::get('logging.channels.papertrail');

        $this->assertIsArray($config);
        $this->assertArrayHasKey('tap', $config);
        $this->assertContains(\App\Logging\ScrubSensitiveData::class, $config['tap']);
    }

    #[Test]
    public function stderr_channel_has_scrub_sensitive_data_tap(): void
    {
        $config = Config::get('logging.channels.stderr');

        $this->assertIsArray($config);
        $this->assertArrayHasKey('tap', $config);
        $this->assertContains(\App\Logging\ScrubSensitiveData::class, $config['tap']);
    }

    #[Test]
    public function syslog_channel_has_scrub_sensitive_data_tap(): void
    {
        $config = Config::get('logging.channels.syslog');

        $this->assertIsArray($config);
        $this->assertArrayHasKey('tap', $config);
        $this->assertContains(\App\Logging\ScrubSensitiveData::class, $config['tap']);
    }

    #[Test]
    public function errorlog_channel_has_scrub_sensitive_data_tap(): void
    {
        $config = Config::get('logging.channels.errorlog');

        $this->assertIsArray($config);
        $this->assertArrayHasKey('tap', $config);
        $this->assertContains(\App\Logging\ScrubSensitiveData::class, $config['tap']);
    }

    #[Test]
    public function slack_channel_has_scrub_sensitive_data_tap(): void
    {
        $config = Config::get('logging.channels.slack');

        $this->assertIsArray($config);
        $this->assertArrayHasKey('tap', $config);
        $this->assertContains(\App\Logging\ScrubSensitiveData::class, $config['tap']);
    }

    #[Test]
    public function daily_json_channel_redacts_sensitive_data_in_log_file(): void
    {
        $logPath = storage_path('logs/test-daily-json.log');

        // Clean up any existing test log file
        if (file_exists($logPath)) {
            unlink($logPath);
        }

        // Configure a temporary test channel
        Config::set('logging.channels.test_daily_json', [
            'driver' => 'single',
            'path' => $logPath,
            'level' => 'debug',
            'formatter' => \Monolog\Formatter\JsonFormatter::class,
            'tap' => [\App\Logging\ScrubSensitiveData::class],
        ]);

        // Log data with fake secrets
        Log::channel('test_daily_json')->info('Test log', [
            'request_id' => 'request-visible-123',
            'headers' => [
                'Authorization' => 'Bearer marker-authorization-secret',
            ],
            'provider' => [
                'access_token' => 'marker-access-token-secret',
            ],
        ]);

        // Read the log file
        $logContent = file_get_contents($logPath);
        $logLines = explode("\n", trim($logContent));
        $logEntry = json_decode($logLines[0], true);

        // Clean up
        unlink($logPath);

        // Verify secrets are redacted
        $this->assertStringNotContainsString('marker-authorization-secret', $logContent);
        $this->assertStringNotContainsString('marker-access-token-secret', $logContent);

        // Verify safe data remains
        $this->assertStringContainsString('request-visible-123', $logContent);
        $this->assertSame('request-visible-123', $logEntry['context']['request_id']);
        $this->assertSame('********', $logEntry['context']['headers']['Authorization']);
        $this->assertSame('********', $logEntry['context']['provider']['access_token']);
    }

    #[Test]
    public function bugsnag_has_required_redacted_keys(): void
    {
        $keys = config('bugsnag.redacted_keys');

        $this->assertIsArray($keys);
        $this->assertContains('access_token', $keys);
        $this->assertContains('refresh_token', $keys);
        $this->assertContains('authorization', $keys);
        $this->assertContains('client_secret', $keys);
        $this->assertContains('code_verifier', $keys);
        $this->assertContains('vendor_auth_code', $keys);
        $this->assertContains('webhook_secret', $keys);
        $this->assertContains('password', $keys);
        $this->assertContains('current_password', $keys);
    }
}
