<?php

declare(strict_types=1);

namespace App\Services\Zoom;

use App\DataTransferObjects\Zoom\AccessTokenDetails;
use App\DataTransferObjects\Zoom\AuthorizationCallbackDetails;
use App\DataTransferObjects\Zoom\AuthorizationRedirectDetails;
use App\DataTransferObjects\Zoom\Meeting;
use App\DataTransferObjects\Zoom\MeetingOperationResult;
use App\Exceptions\Integrations\Zoom\ZoomException;
use App\Interfaces\Zoom;
use App\Models\User;
use Faker\Generator;
use Illuminate\Support\Collection;
use Override;
use PHPUnit\Framework\Assert;

/**
 * @template TKey of array-key
 * @template TValue
 */
final class ZoomServiceFake extends ZoomOAuthService implements Zoom
{
    /**
     * @var Collection<int, array<string, mixed>>
     */
    public Collection $meetingsToCreate;

    public string $authorizationUrl;

    public string $state;

    public string $codeVerifier;

    private ?ZoomException $failureException = null;

    public function __construct()
    {
        parent::__construct(null);
        $this->meetingsToCreate = new Collection;
    }

    #[Override]
    public function getAuthRedirectDetails(): AuthorizationRedirectDetails
    {
        return new AuthorizationRedirectDetails(
            authorizationUrl: $this->authorizationUrl,
            state: $this->state,
            codeVerifier: $this->codeVerifier,
        );
    }

    #[Override]
    public function authorize(
        AuthorizationCallbackDetails $callbackDetails
    ): AccessTokenDetails {
        if ($this->failureException instanceof ZoomException) {
            throw $this->failureException;
        }

        return new AccessTokenDetails(
            accessToken: 'access-token-here',
            refreshToken: 'refresh-token-here',
            expiresAt: now()->addWeek()->toDateTimeImmutable(),
        );
    }

    /**
     * @return self<array-key, array<string, mixed>>
     */
    public function shouldFailWithException(ZoomException $exception): self
    {
        $this->failureException = $exception;

        return $this;
    }

    /**
     * @return self<array-key, array<string, mixed>>
     */
    public function buildAuthorizationUrlUsing(
        string $authorizationUrl,
        string $state,
        string $codeVerifier
    ): self {
        $this->authorizationUrl = $authorizationUrl;
        $this->state = $state;
        $this->codeVerifier = $codeVerifier;

        return $this;
    }

    /**
     * @param  array<string, mixed>  $validated
     */
    #[Override]
    public function createMeeting(array $validated, User $user): Meeting
    {
        if ($this->failureException instanceof ZoomException) {
            throw $this->failureException;
        }
        $this->meetingsToCreate->push($validated);

        return $this->fakeMeeting();
    }

    /**
     * @param  array<string, mixed>  $validated
     */
    #[Override]
    public function updateMeeting(array $validated, User $user): MeetingOperationResult
    {
        if ($this->failureException instanceof ZoomException) {
            throw $this->failureException;
        }

        return MeetingOperationResult::updated((int) ($validated['meeting_id'] ?? 0), 204);
    }

    #[Override]
    public function deleteMeeting(int $meetingId, User $user): MeetingOperationResult
    {
        if ($this->failureException instanceof ZoomException) {
            throw $this->failureException;
        }

        return MeetingOperationResult::deleted($meetingId, 204);
    }

    #[Override]
    public function getZakToken(User $user): string
    {
        return 'zak&token';
    }

    public function assertNoMeetingsCreated(): void
    {
        Assert::assertEmpty($this->meetingsToCreate, 'Meeting was not created.');
    }

    public function assertMeetingCreated(string $topic, string $agenda, int $duration): void
    {
        $meetingIsToBeCreated = $this->meetingsToCreate
            ->where('topic', $topic)
            ->where('agenda', $agenda)
            ->where('duration', $duration)
            ->isNotEmpty();
        Assert::assertTrue($meetingIsToBeCreated, 'Meetings were created.');
    }

    private function fakeMeeting(): Meeting
    {
        app(Generator::class);

        return new Meeting(
            meeting_id: 1234,
            topic: 'Topic Of Meeting',
            agenda: 'this is the agenda of meeting',
            created_at: '2024-05-18 18:00:07',
            duration: 30,
            start_time: '2024-05-27 18:00:07',
            start_url: 'https://zoom.us/s/1234567890?pwd=fake-test-password', // Test fixture; no real credentials. NOSONAR
            join_url: 'https://zoom.us/j/1234567890?pwd=fake-test-password', // Test fixture; no real credentials. NOSONAR
            status: 'waiting',
            timezone: 'UTC',
            password: 'herpku',
            join_before_host: false,
        );
    }
}
