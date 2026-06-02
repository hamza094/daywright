<?php

declare(strict_types=1);

namespace Tests\Feature\Api\Middleware\Zoom;

use App\Http\Middleware\VerifyZoomWebhook;
use Illuminate\Http\Request;
use Illuminate\Testing\TestResponse;
use Override;
use Route;
use Symfony\Component\HttpFoundation\Response;
use Tests\TestCase;
use WendellAdriel\Idempotency\Enums\IdempotencyScope;
use WendellAdriel\Idempotency\Http\Middleware\Idempotent;

use function Safe\json_encode;

class VerifyWebhookTest extends TestCase
{
    private const string WEBHOOK_TEST_PATH = '/_test/webhook';

    private const string IDEMPOTENT_WEBHOOK_TEST_PATH = '/_test/idempotent-webhook';

    public $payload;

    public $timestamp;

    #[Override]
    protected function setUp(): void
    {
        parent::setUp();

        // Ensure the webhook secret is set for signature generation in tests
        config(['services.zoom.webhook_secret' => 'secret']);

        Route::middleware('zoom.webhook')->any(self::WEBHOOK_TEST_PATH, fn (): string => 'OK');

        Route::middleware([
            VerifyZoomWebhook::class,
            Idempotent::using(scope: IdempotencyScope::Global),
        ])->any(
            self::IDEMPOTENT_WEBHOOK_TEST_PATH,
            fn (Request $request): string => (string) $request->header((string) config('idempotency.header')),
        );

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
        $this->withExceptionHandling();

        $timestamp = (string) time();

        $response = $this->postJson(self::WEBHOOK_TEST_PATH, $this->payload, [
            'x-zm-request-timestamp' => $timestamp,
            'x-zm-signature' => 'invalid-signature',
            'x-zm-request-id' => 'zoom-request-invalid-signature',
        ]);

        $response->assertStatus(Response::HTTP_FORBIDDEN)
            ->assertJsonPath('message', 'The webhook signature was invalid.');
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
            'x-zm-request-id' => 'zoom-request-123',
        ]);

        $this->assertEquals('OK', $response->getContent());
    }

    /** @test */
    public function it_verifies_the_exact_raw_request_body(): void
    {
        $timestamp = (string) time();
        $rawPayload = <<<'JSON'
{
    "event": "meeting.started",
    "payload": {
        "object": {
            "id": "meeting_id"
        }
    }
}
JSON;

        $response = $this->postRawJson(self::WEBHOOK_TEST_PATH, $rawPayload, [
            'x-zm-request-timestamp' => $timestamp,
            'x-zm-signature' => $this->buildSignature($timestamp, $rawPayload),
            'x-zm-request-id' => 'zoom-request-raw-body',
        ]);

        $response->assertOk();
        $this->assertSame('OK', $response->getContent());
    }

    /** @test */
    public function it_fails_with_an_old_timestamp(): void
    {
        $this->withExceptionHandling();

        $oldTimestamp = (string) ($this->timestamp - 600);

        $signature = $this->buildSignature($oldTimestamp, $this->payload);

        $this->postJson(self::WEBHOOK_TEST_PATH, $this->payload, [
            'x-zm-request-timestamp' => $oldTimestamp,
            'x-zm-signature' => $signature,
            'x-zm-request-id' => 'zoom-request-old-timestamp',
        ])
            ->assertStatus(Response::HTTP_FORBIDDEN)
            ->assertJsonPath('message', 'The webhook signature was invalid.');
    }

    /** @test */
    public function it_maps_the_zoom_request_id_to_the_idempotency_header(): void
    {
        $timestamp = (string) time();
        $zoomRequestId = '6009d653_d487_445d_8406_42b654974899';

        $signature = $this->buildSignature($timestamp, $this->payload);

        $response = $this->postJson(self::IDEMPOTENT_WEBHOOK_TEST_PATH, $this->payload, [
            'x-zm-request-timestamp' => $timestamp,
            'x-zm-signature' => $signature,
            'x-zm-request-id' => $zoomRequestId,
        ]);

        $response->assertOk();
        $this->assertSame($zoomRequestId, $response->getContent());
    }

    /** @test */
    public function it_returns_the_zoom_endpoint_validation_payload(): void
    {
        $timestamp = (string) time();
        $plainToken = 'zoom-plain-token';
        $payload = [
            'event' => 'endpoint.url_validation',
            'payload' => [
                'plainToken' => $plainToken,
            ],
        ];

        $signature = $this->buildSignature($timestamp, $payload);

        $response = $this->postJson(self::WEBHOOK_TEST_PATH, $payload, [
            'x-zm-request-timestamp' => $timestamp,
            'x-zm-signature' => $signature,
            'x-zm-request-id' => 'zoom-request-endpoint-validation',
        ]);

        $response->assertOk()
            ->assertExactJson([
                'plainToken' => $plainToken,
                'encryptedToken' => hash_hmac('sha256', $plainToken, 'secret'),
            ]);
    }

    /** @test */
    public function it_requires_the_zoom_request_id_header(): void
    {
        $this->withExceptionHandling();

        $timestamp = (string) time();
        $signature = $this->buildSignature($timestamp, $this->payload);

        $this->postJson(self::WEBHOOK_TEST_PATH, $this->payload, [
            'x-zm-request-timestamp' => $timestamp,
            'x-zm-signature' => $signature,
        ])
            ->assertStatus(Response::HTTP_BAD_REQUEST)
            ->assertJsonPath('message', 'Missing required Zoom webhook header: x-zm-request-id.');
    }

    protected function buildSignature(string $timestamp, array|string $payload): string
    {
        $message = 'v0:'.$timestamp.':'.$this->normalizePayload($payload);

        return 'v0='.hash_hmac('sha256', $message, (string) config('services.zoom.webhook_secret'));
    }

    private function normalizePayload(array|string $payload): string
    {
        if (is_string($payload)) {
            return $payload;
        }

        return json_encode($payload);
    }

    private function postRawJson(string $path, string $payload, array $headers): TestResponse
    {
        return $this->call('POST', $path, [], [], [], $this->transformHeadersToServerVars([
            'Accept' => 'application/json',
            'Content-Type' => 'application/json',
            ...$headers,
        ]), $payload);
    }
}
