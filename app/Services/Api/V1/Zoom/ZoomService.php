<?php

declare(strict_types=1);

namespace App\Services\Api\V1\Zoom;

use App\DataTransferObjects\Zoom\AccessTokenDetails;
use App\DataTransferObjects\Zoom\AuthorizationCallbackDetails;
use App\DataTransferObjects\Zoom\AuthorizationRedirectDetails;
use App\DataTransferObjects\Zoom\Meeting;
use App\Exceptions\Integrations\Zoom\ZoomException;
use App\Http\Integrations\Zoom\Requests\CreateMeeting;
use App\Http\Integrations\Zoom\Requests\DeleteMeeting;
use App\Http\Integrations\Zoom\Requests\GetAccessTokenRequest;
use App\Http\Integrations\Zoom\Requests\GetZakToken;
use App\Http\Integrations\Zoom\Requests\UpdateMeeting;
use App\Http\Integrations\Zoom\ZoomConnector;
use App\Interfaces\Zoom;
use App\Models\User;
use App\Models\UserZoomConnection;
use Illuminate\Support\Str;
use Override;
use Saloon\Http\Auth\AccessTokenAuthenticator;
use Saloon\Http\Response as SaloonResponse;

final class ZoomService implements Zoom
{
    private const string USER_NOT_CONNECTED = 'User is not connected to Zoom.';

    #[Override]
    public function getAuthRedirectDetails(): AuthorizationRedirectDetails
    {
        $codeVerifier = Str::random(random_int(43, 128));

        $codeChallenge = hash('sha256', $codeVerifier, true);

        $codeChallenge = strtr(base64_encode($codeChallenge), '+/', '-_');

        $codeChallenge = trim($codeChallenge, '=');

        $connector = $this->connector();

        $authorizationUrl = $connector->getAuthorizationUrl(
            scopeSeparator: ',',
            additionalQueryParameters: [
                'code_challenge' => $codeChallenge,
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

        $tokenDetails = $this->connector()->getAccessToken(
            code: $callbackDetails->authorizationCode,
            state: $callbackDetails->state,
            expectedState: $callbackDetails->expectedState,
            requestModifier: function (
                GetAccessTokenRequest $saloonRequest
            ) use ($callbackDetails): void {
                $saloonRequest->body()->add(
                    'code_verifier',
                    $callbackDetails->codeVerifier,
                );
            }
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
        return $this->connectorForUser($user)
            ->send(new CreateMeeting($validated))
            ->dtoOrFail();
    }

    /**
     * @param  array<string, mixed>  $validated
     */
    #[Override]
    public function updateMeeting(array $validated, User $user): SaloonResponse
    {
        return $this->connectorForUser($user)
            ->send(new UpdateMeeting($validated))
            ->throw();
    }

    #[Override]
    public function deleteMeeting(int $meetingId, User $user): SaloonResponse
    {
        return $this->connectorForUser($user)
            ->send(new DeleteMeeting($meetingId))
            ->throw();
    }

    #[Override]
    public function getZakToken(User $user): string
    {
        $response = $this->connectorForUser($user)
            ->send(new GetZakToken)
            ->json();

        return $response['token'];
    }

    private function connector(): ZoomConnector
    {
        return new ZoomConnector;
    }

    private function connectorForUser(User $user): ZoomConnector
    {
        $accessTokenDetails = $this->getZoomOAuthDetails($user);

        $connector = $this->connector()->authenticate($accessTokenDetails);

        if ($accessTokenDetails->hasExpired()) {

            $newAccessTokenDetails =

            $connector->refreshAccessToken($accessTokenDetails);

            $connector->authenticate($newAccessTokenDetails);

            $this->updateZoomOAuthDetails($newAccessTokenDetails, $user);
        }

        return $connector;
    }

    private function getZoomOAuthDetails(User $user): AccessTokenAuthenticator
    {
        $zoomConnection = $this->getZoomConnection($user);

        return new AccessTokenAuthenticator(
            $zoomConnection->access_token,
            $zoomConnection->refresh_token,
            $zoomConnection->expires_at->toDateTimeImmutable(),
        );
    }

    private function getZoomConnection(User $user): UserZoomConnection
    {
        $user->loadMissing('zoomConnection');

        $zoomConnection = $user->zoomConnection;

        if (! $zoomConnection instanceof UserZoomConnection || ! $zoomConnection->hasValidCredentials()) {
            throw new ZoomException(self::USER_NOT_CONNECTED);
        }

        return $zoomConnection;
    }

    private function updateZoomOAuthDetails(
        AccessTokenAuthenticator $newAccessTokenDetails,
        User $user
    ): void {
        $user->updateZoomOAuthDetails(
            $newAccessTokenDetails->getAccessToken(),
            $newAccessTokenDetails->getRefreshToken(),
            $newAccessTokenDetails->getExpiresAt(),
        );
    }
}
