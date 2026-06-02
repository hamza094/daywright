<?php

declare(strict_types=1);

namespace App\Services\Zoom;

use App\DataTransferObjects\Zoom\AccessTokenDetails;
use App\DataTransferObjects\Zoom\AuthorizationCallbackDetails;
use App\DataTransferObjects\Zoom\AuthorizationRedirectDetails;
use App\DataTransferObjects\Zoom\Meeting;
use App\Exceptions\Integrations\Zoom\ZoomUserErrorException;
use App\Http\Integrations\Zoom\Requests\CreateMeeting;
use App\Http\Integrations\Zoom\Requests\DeleteMeeting;
use App\Http\Integrations\Zoom\Requests\GetAccessTokenRequest;
use App\Http\Integrations\Zoom\Requests\GetZakToken;
use App\Http\Integrations\Zoom\Requests\UpdateMeeting;
use App\Http\Integrations\Zoom\ZoomConnector;
use App\Interfaces\Zoom;
use App\Models\User;
use Closure;
use Illuminate\Support\Str;
use Override;
use Saloon\Http\Response as SaloonResponse;

final class ZoomService implements Zoom
{
    private const string USER_NOT_CONNECTED = 'User is not connected to Zoom.';

    public function __construct(private readonly ZoomConnectorManager $connectors) {}

    #[Override]
    public function getAuthRedirectDetails(): AuthorizationRedirectDetails
    {
        $codeVerifier = Str::random(random_int(43, 128));

        $connector = $this->connectors->connector();

        $authorizationUrl = $connector->getAuthorizationUrl(
            scopeSeparator: ',',
            additionalQueryParameters: [
                'code_challenge' => $this->codeChallenge($codeVerifier),
                'code_challenge_method' => 'S256',
            ]
        );

        return new AuthorizationRedirectDetails(
            authorizationUrl: $authorizationUrl,
            state: $connector->getState(),
            codeVerifier: $codeVerifier,
        );
    }

    #[Override]
    public function authorize(
        AuthorizationCallbackDetails $callbackDetails
    ): AccessTokenDetails {
        /** @var AccessTokenDetails $tokenDetails */
        $tokenDetails = $this->connectors->connector()->getAccessToken(
            code: $callbackDetails->authorizationCode,
            state: $callbackDetails->state,
            expectedState: $callbackDetails->expectedState,
            requestModifier: $this->codeVerifierRequestModifier($callbackDetails->codeVerifier),
        );

        return new AccessTokenDetails(
            accessToken: $tokenDetails->accessToken,
            refreshToken: $tokenDetails->refreshToken,
            expiresAt: $tokenDetails->expiresAt,
        );
    }

    /**
     * @param  array<string, mixed>  $validated
     */
    #[Override]
    public function createMeeting(array $validated, User $user): Meeting
    {
        return $this->connectedConnector($user)
            ->send(new CreateMeeting($validated))
            ->dtoOrFail();
    }

    /**
     * @param  array<string, mixed>  $validated
     */
    #[Override]
    public function updateMeeting(array $validated, User $user): SaloonResponse
    {
        return $this->connectedConnector($user)
            ->send(new UpdateMeeting($validated))
            ->throw();
    }

    #[Override]
    public function deleteMeeting(int $meetingId, User $user): SaloonResponse
    {
        return $this->connectedConnector($user)
            ->send(new DeleteMeeting($meetingId))
            ->throw();
    }

    #[Override]
    public function getZakToken(User $user): string
    {
        $response = $this->connectedConnector($user)
            ->send(new GetZakToken)
            ->json();

        return $response['token'];
    }

    private function connectedConnector(User $user): ZoomConnector
    {
        $this->ensureZoomConnection($user);

        return $this->connectors->forUser($user);
    }

    private function ensureZoomConnection(User $user): void
    {
        if (! $user->isConnectedToZoom()) {
            throw new ZoomUserErrorException(self::USER_NOT_CONNECTED);
        }
    }

    private function codeChallenge(string $codeVerifier): string
    {
        $hashedVerifier = hash('sha256', $codeVerifier, true);

        return trim(strtr(base64_encode($hashedVerifier), '+/', '-_'), '=');
    }

    /**
     * @return Closure(GetAccessTokenRequest): void
     */
    private function codeVerifierRequestModifier(string $codeVerifier): Closure
    {
        return static function (GetAccessTokenRequest $request) use ($codeVerifier): void {
            $request->body()->add('code_verifier', $codeVerifier);
        };
    }
}
