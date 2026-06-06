<?php

declare(strict_types=1);

namespace App\Services\Zoom;

use App\DataTransferObjects\Zoom\AccessTokenDetails;
use App\DataTransferObjects\Zoom\AuthorizationCallbackDetails;
use App\DataTransferObjects\Zoom\AuthorizationRedirectDetails;
use App\DataTransferObjects\Zoom\Meeting;
use App\DataTransferObjects\Zoom\MeetingOperationResult;
use App\Http\Integrations\Zoom\Requests\CreateMeeting;
use App\Http\Integrations\Zoom\Requests\DeleteMeeting;
use App\Http\Integrations\Zoom\Requests\GetZakToken;
use App\Http\Integrations\Zoom\Requests\UpdateMeeting;
use App\Http\Integrations\Zoom\ZoomConnector;
use App\Interfaces\Zoom;
use App\Models\User;
use App\Repository\OAuthConnectionRepository;
use Override;

final class ZoomService implements Zoom
{
    private const string USER_NOT_CONNECTED = 'User is not connected to Zoom.';

    public function __construct(
        private readonly ZoomConnectorManager $connectors,
        private readonly ZoomOAuthService $oauthService,
        private readonly OAuthConnectionRepository $oauthRepository,
    ) {}

    #[Override]
    public function getAuthRedirectDetails(): AuthorizationRedirectDetails
    {
        return $this->oauthService->getAuthRedirectDetails();
    }

    #[Override]
    public function authorize(
        AuthorizationCallbackDetails $callbackDetails
    ): AccessTokenDetails {
        return $this->oauthService->authorize($callbackDetails);
    }

    /**
     * @param  array<string, mixed>  $validated
     */
    #[Override]
    public function createMeeting(array $validated, User $user): Meeting
    {
        return $this->connectedConnector($user)
            ->send(new CreateMeeting($validated, $this->limiterKey($user)))
            ->dtoOrFail();
    }

    /**
     * @param  array<string, mixed>  $validated
     */
    #[Override]
    public function updateMeeting(array $validated, User $user): MeetingOperationResult
    {
        $meetingId = (int) $validated['meeting_id'];
        $response = $this->connectedConnector($user)
            ->send(new UpdateMeeting($validated, $this->limiterKey($user)))
            ->throw();

        return MeetingOperationResult::updated($meetingId, $response->status());
    }

    #[Override]
    public function deleteMeeting(int $meetingId, User $user): MeetingOperationResult
    {
        $response = $this->connectedConnector($user)
            ->send(new DeleteMeeting($meetingId, $this->limiterKey($user)))
            ->throw();

        return MeetingOperationResult::deleted($meetingId, $response->status());
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
        return $this->connectors->forUser($user);
    }

    private function limiterKey(User $user): string
    {
        return ZoomLimiter::forUser($user);
    }
}
