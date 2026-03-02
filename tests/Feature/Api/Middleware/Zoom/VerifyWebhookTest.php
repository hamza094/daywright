<?php

declare(strict_types=1);

namespace Tests\Feature\Api\Middleware\Zoom;

use Override;
use Route;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\TestCase;

use function Safe\json_encode;

class VerifyWebhookTest extends TestCase
{
    private const string WEBHOOK_TEST_PATH = '/_test/webhook';

    public $payload;

    public $timestamp;

    #[Override]
    protected function setUp(): void
    {
        parent::setUp();

        // Ensure the webhook secret is set for signature generation in tests
        config(['services.zoom.webhook_secret' => 'secret']);

        Route::middleware('zoom.webhook')->any(self::WEBHOOK_TEST_PATH, fn (): string => 'OK');

        $this->payload = [
            'event' => 'meeting.started',
            'payload' => [
                'object' => [
                    'id' => 'meeting_id',
                ],
            ],
        ];

        $this->timestamp = time();

        $this->withoutExceptionHandling();

    }

    /** @test */
    public function it_aborts_with_an_invalid_signature(): void
    {
        try {
            $this->post(self::WEBHOOK_TEST_PATH, $this->payload, [
                'x-zm-request-timestamp' => $this->timestamp,
                'x-zm-signature' => 'invalid-signature',
            ]);
        } catch (HttpException $e) {
            $this->assertEquals(Response::HTTP_FORBIDDEN, $e->getStatusCode());
            $this->assertEquals('The webhook signature was invalid.', $e->getMessage());

            return;
        }

        $this->fail('Expected the webhook signature to be invalid.');
    }

    /** @test */
    public function it_passes_with_a_valid_signature(): void
    {
        // Zoom sends timestamp as a numeric string header; use the same value for header and signature
        $timestamp = (string) time();

        $signature = $this->buildSignature($timestamp, $this->payload);

        $response = $this->postJson(self::WEBHOOK_TEST_PATH, $this->payload, [
            'x-zm-request-timestamp' => $timestamp,
            'x-zm-signature' => $signature,
        ]);

        $this->assertEquals('OK', $response->getContent());
    }

    /** @test */
    public function it_fails_with_an_old_timestamp(): void
    {

        $oldTimestamp = (string) ($this->timestamp - 600);

        $signature = $this->buildSignature($oldTimestamp, $this->payload);

        try {
            $response = $this->postJson(self::WEBHOOK_TEST_PATH, $this->payload, [
                'x-zm-request-timestamp' => $oldTimestamp,
                'x-zm-signature' => $signature,
            ]);
        } catch (HttpException $e) {
            $this->assertEquals(Response::HTTP_FORBIDDEN, $e->getStatusCode());
            $this->assertEquals('The webhook signature was invalid.', $e->getMessage());

            return;
        }

        $this->fail('The timestamp should have failed verification.');
    }

    protected function buildSignature(string $timestamp, $payload): string
    {
        $message = 'v0:'.$timestamp.':'.json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);

        return 'v0='.hash_hmac('sha256', $message, (string) config('services.zoom.webhook_secret'));
    }
}
