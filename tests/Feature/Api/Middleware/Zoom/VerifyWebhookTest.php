<?php

declare(strict_types=1);

namespace Tests\Feature\Api\Middleware\Zoom;

use App\Http\Middleware\VerifyZoomWebhook;
use Illuminate\Http\Request;
use Override;
use Route;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\HttpException;
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
        try {
            $this->post(self::WEBHOOK_TEST_PATH, $this->payload, [
                'x-zm-request-timestamp' => $this->timestamp,
                'x-zm-signature' => 'invalid-signature',
                'x-zm-request-id' => 'zoom-request-invalid-signature',
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
            'x-zm-request-id' => 'zoom-request-123',
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
                'x-zm-request-id' => 'zoom-request-old-timestamp',
            ]);
        } catch (HttpException $e) {
            $this->assertEquals(Response::HTTP_FORBIDDEN, $e->getStatusCode());
            $this->assertEquals('The webhook signature was invalid.', $e->getMessage());

            return;
        }

        $this->fail('The timestamp should have failed verification.');
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
    public function it_requires_the_zoom_request_id_header(): void
    {
        $timestamp = (string) time();
        $signature = $this->buildSignature($timestamp, $this->payload);

        try {
            $this->postJson(self::WEBHOOK_TEST_PATH, $this->payload, [
                'x-zm-request-timestamp' => $timestamp,
                'x-zm-signature' => $signature,
            ]);
        } catch (HttpException $e) {
            $this->assertEquals(Response::HTTP_BAD_REQUEST, $e->getStatusCode());
            $this->assertEquals('Missing required Zoom webhook header: x-zm-request-id.', $e->getMessage());

            return;
        }

        $this->fail('Expected the Zoom request id to be required.');
    }

    protected function buildSignature(string $timestamp, $payload): string
    {
        $message = 'v0:'.$timestamp.':'.json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);

        return 'v0='.hash_hmac('sha256', $message, (string) config('services.zoom.webhook_secret'));
    }
}
