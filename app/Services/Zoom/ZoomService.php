<?php

declare(strict_types=1);

namespace App\Services\Zoom;

use App\DataTransferObjects\Zoom\AccessTokenDetails;
use App\DataTransferObjects\Zoom\AuthorizationCallbackDetails;
use App\DataTransferObjects\Zoom\AuthorizationRedirectDetails;
use App\DataTransferObjects\Zoom\Meeting;
use App\Http\Integrations\Zoom\Requests\CreateMeeting;
use App\Http\Integrations\Zoom\Requests\DeleteMeeting;
use App\Http\Integrations\Zoom\Requests\GetZakToken;
use App\Http\Integrations\Zoom\Requests\UpdateMeeting;
use App\Http\Integrations\Zoom\ZoomConnector;
use App\Interfaces\Zoom;
use App\Models\User;
use Override;

final readonly class ZoomService implements Zoom
{
    public function __construct(
        private ZoomConnectorManager $connectors,
        private ZoomOAuthService $oauthService,
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
    public function updateMeeting(array $validated, User $user): void
    {
        $this->connectedConnector($user)
            ->send(new UpdateMeeting($validated, $this->limiterKey($user)))
            ->throw();
    }

    #[Override]
    public function deleteMeeting(int $meetingId, User $user): void
    {
        $this->connectedConnector($user)
            ->send(new DeleteMeeting($meetingId, $this->limiterKey($user)))
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
        return $this->connectors->forUser($user);
    }

    private function limiterKey(User $user): string
    {
        return ZoomLimiter::forUser($user);
    }
}
