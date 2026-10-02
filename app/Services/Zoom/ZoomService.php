<?php

declare(strict_types=1);

namespace App\Services\Zoom;

use App\DataTransferObjects\OAuth\OAuthTokens;
use App\DataTransferObjects\Zoom\AuthorizationCallbackDetails;
use App\DataTransferObjects\Zoom\AuthorizationRedirectDetails;
use App\DataTransferObjects\Zoom\Meeting;
use App\DataTransferObjects\Zoom\MeetingSummary;
use App\Exceptions\Integrations\Zoom\NotFoundException;
use App\Exceptions\Integrations\Zoom\ZoomExternalFailureException;
use App\Exceptions\Integrations\Zoom\ZoomMeetingCreationUnknownException;
use App\Http\Integrations\Zoom\Requests\CreateMeeting;
use App\Http\Integrations\Zoom\Requests\DeleteMeeting;
use App\Http\Integrations\Zoom\Requests\GetMeeting;
use App\Http\Integrations\Zoom\Requests\GetZakToken;
use App\Http\Integrations\Zoom\Requests\ListMeetings;
use App\Http\Integrations\Zoom\Requests\UpdateMeeting;
use App\Http\Integrations\Zoom\ZoomConnector;
use App\Interfaces\Zoom;
use App\Models\User;
use Override;
use Saloon\Exceptions\Request\FatalRequestException;
use Saloon\RateLimitPlugin\Exceptions\RateLimitReachedException;
use Throwable;

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
    ): OAuthTokens {
        return $this->oauthService->authorize($callbackDetails);
    }

    /**
     * @param  array<string, mixed>  $validated
     */
    #[Override]
    public function createMeeting(array $validated, User $user, string $operationId): Meeting
    {
        try {
            $response = $this->connectedConnector($user)
                ->send(new CreateMeeting($validated, $operationId, $this->limiterKey($user)));
            $response->throw();

            return $response->dto();
        } catch (RateLimitReachedException $exception) {
            $retryAfter = $exception->getLimit()->getRemainingSeconds();

            $zoomException = new ZoomExternalFailureException(
                'Zoom meeting creation was rate limited.',
                429,
                previous: $exception,
            );

            throw $zoomException->withContext(['retry_after_seconds' => $retryAfter]);
        } catch (ZoomExternalFailureException|FatalRequestException $exception) {
            if ($this->isUncertainOutcome($exception)) {
                throw new ZoomMeetingCreationUnknownException(
                    'Zoom meeting creation result is uncertain',
                    $exception->getCode(),
                    previous: $exception
                );
            }

            throw $exception;
        }
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

    #[Override]
    public function getMeeting(int|string $meetingId, User $user): ?Meeting
    {
        try {
            $response = $this->connectedConnector($user)
                ->send(new GetMeeting($meetingId, $this->limiterKey($user)));
            $response->throw();

            return Meeting::fromResponse($response->json());
        } catch (NotFoundException) {
            return null;
        }
    }

    #[Override]
    /**
     * @return list<MeetingSummary>
     */
    public function listMeetings(User $user): array
    {
        $meetings = [];
        $nextPageToken = null;

        do {
            $response = $this->connectedConnector($user)
                ->send(new ListMeetings($this->limiterKey($user), $nextPageToken));
            $response->throw();

            $items = $response->json('meetings', []);

            if (! is_array($items)) {
                break;
            }

            foreach ($items as $item) {
                if (is_array($item)) {
                    $meetings[] = MeetingSummary::fromResponse($item);
                }
            }

            $token = $response->json('next_page_token');
            $nextPageToken = is_string($token) && $token !== '' ? $token : null;
        } while ($nextPageToken !== null);

        return $meetings;
    }

    private function isUncertainOutcome(Throwable $exception): bool
    {
        $code = $exception->getCode();

        return $exception instanceof FatalRequestException || $code >= 500 || $code === 0;
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
