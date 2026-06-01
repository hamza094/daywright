<?php

declare(strict_types=1);

namespace Tests\Feature\Api\Actions;

use App\Actions\CreateZoomJwtAction;
use Firebase\JWT\JWT;
use Firebase\JWT\Key;
use Illuminate\Support\Facades\Config;
use Override;
use Tests\TestCase;

class ZoomActionTest extends TestCase
{
    protected $sdkKey;

    protected $sdkSecret;

    protected $action;

    #[Override]
    protected function setUp(): void
    {
        parent::setUp();

        Config::set('services.zoom.client_id', 'fake-key');

        // Use a sufficiently long secret to satisfy JWT library minimum key length checks
        Config::set('services.zoom.client_secret', str_repeat('a', 64));

        $this->sdkKey = config('services.zoom.client_id');

        $this->sdkSecret = config('services.zoom.client_secret');

        $this->action = new CreateZoomJwtAction($this->sdkKey, $this->sdkSecret);
    }

    /** @test */
    public function it_generates_a_valid_jwt_token(): void
    {
        $meetingNumber = 1234567890;
        $role = 1;

        $token = $this->action->execute($meetingNumber, $role);

        $decodedToken = JWT::decode($token, new Key($this->sdkSecret, 'HS256'));

        $this->assertEquals($this->sdkKey, $decodedToken->sdkKey);
        $this->assertEquals($meetingNumber, $decodedToken->mn);
        $this->assertEquals($role, $decodedToken->role);
    }
}
