<?php

declare(strict_types=1);

namespace App\Services\Zoom;

use App\DataTransferObjects\OAuth\OAuthTokens;
use App\DataTransferObjects\Zoom\AuthorizationCallbackDetails;
use App\DataTransferObjects\Zoom\AuthorizationRedirectDetails;
use App\DataTransferObjects\Zoom\Meeting;
use App\DataTransferObjects\Zoom\MeetingSummary;
use App\Interfaces\Zoom;
use App\Models\User;
use App\Repository\OAuthConnectionRepository;
use Closure;
use Illuminate\Support\Collection;
use Override;
use PHPUnit\Framework\Assert;
use Throwable;

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

    /**
     * @var Collection<int, array<string, mixed>>
     */
    public Collection $meetingsToUpdate;

    /**
     * @var Collection<int, int>
     */
    public Collection $meetingsToDelete;

    public string $authorizationUrl;

    public string $state;

    public string $codeVerifier;

    private ?Throwable $failureException = null;

    private ?Meeting $meetingToFind = null;

    private ?Closure $beforeFindMeeting = null;

    private ?Closure $beforeUpdateMeeting = null;

    private ?Throwable $updateFailureException = null;

    /** @var list<MeetingSummary> */
    private array $meetingsToList = [];

    public function __construct(private readonly OAuthConnectionRepository $oauthRepository)
    {
        $connectorManager = new ZoomConnectorManager($this->oauthRepository);
        parent::__construct($connectorManager);
        $this->meetingsToCreate = new Collection;
        $this->meetingsToUpdate = new Collection;
        $this->meetingsToDelete = new Collection;
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
    ): OAuthTokens {
        if ($this->failureException instanceof Throwable) {
            throw $this->failureException;
        }

        return new OAuthTokens(
            accessToken: 'access-token-here',
            refreshToken: 'refresh-token-here',
            expiresAt: now()->addWeek()->toDateTimeImmutable(),
        );
    }

    /**
     * @return self<array-key, array<string, mixed>>
     */
    public function shouldFailWithException(Throwable $exception): self
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
    public function createMeeting(array $validated, User $user, string $operationId): Meeting
    {
        if ($this->failureException instanceof Throwable) {
            throw $this->failureException;
        }
        $this->meetingsToCreate->push([...$validated, 'operation_id' => $operationId]);

        return $this->fakeMeeting();
    }

    /**
     * @param  array<string, mixed>  $validated
     */
    #[Override]
    public function updateMeeting(array $validated, User $user): void
    {
        if ($this->beforeUpdateMeeting !== null) {
            ($this->beforeUpdateMeeting)();
            $this->beforeUpdateMeeting = null;
        }

        if ($this->updateFailureException instanceof Throwable) {
            $exception = $this->updateFailureException;
            $this->updateFailureException = null;

            throw $exception;
        }

        if ($this->failureException instanceof Throwable) {
            throw $this->failureException;
        }

        $this->meetingsToUpdate->push($validated);

        if ($this->meetingToFind !== null) {
            $this->meetingToFind = $this->updatedMeeting($this->meetingToFind, $validated);
        }
    }

    #[Override]
    public function deleteMeeting(int $meetingId, User $user): void
    {
        if ($this->failureException instanceof Throwable) {
            throw $this->failureException;
        }

        $this->meetingsToDelete->push($meetingId);
    }

    #[Override]
    public function getZakToken(User $user): string
    {
        return 'zak&token';
    }

    #[Override]
    public function getMeeting(int|string $meetingId, User $user): ?Meeting
    {
        if ($this->beforeFindMeeting !== null) {
            ($this->beforeFindMeeting)();
            $this->beforeFindMeeting = null;
        }

        if ($this->failureException instanceof Throwable) {
            throw $this->failureException;
        }

        return $this->meetingToFind;
    }

    #[Override]
    /**
     * @return list<MeetingSummary>
     */
    public function listMeetings(User $user): array
    {
        if ($this->failureException instanceof Throwable) {
            throw $this->failureException;
        }

        return $this->meetingsToList;
    }

    /**
     * @return self<array-key, array<string, mixed>>
     */
    public function findsMeeting(?Meeting $meeting): self
    {
        $this->meetingToFind = $meeting;

        return $this;
    }

    /**
     * @param  Closure(): void  $callback
     */
    public function beforeFindingMeeting(Closure $callback): self
    {
        $this->beforeFindMeeting = $callback;

        return $this;
    }

    /**
     * @param  Closure(): void  $callback
     */
    public function beforeUpdatingMeeting(Closure $callback): self
    {
        $this->beforeUpdateMeeting = $callback;

        return $this;
    }

    public function failNextUpdateWithException(Throwable $exception): self
    {
        $this->updateFailureException = $exception;

        return $this;
    }

    /**
     * @return self<array-key, array<string, mixed>>
     */
    public function meetingNotFound(): self
    {
        $this->meetingToFind = null;

        return $this;
    }

    /**
     * @param  list<MeetingSummary>  $meetings
     * @return self<array-key, array<string, mixed>>
     */
    public function listsMeetings(array $meetings): self
    {
        $this->meetingsToList = $meetings;

        return $this;
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

    public function assertNoMeetingsDeleted(): void
    {
        Assert::assertEmpty($this->meetingsToDelete, 'deleteMeeting was called when it should not have been.');
    }

    private function fakeMeeting(): Meeting
    {
        return new Meeting(
            meeting_id: random_int(10000000, 99999999),
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

    /**
     * @param  array<string, mixed>  $validated
     */
    private function updatedMeeting(Meeting $meeting, array $validated): Meeting
    {
        return new Meeting(
            meeting_id: $meeting->meeting_id,
            topic: $validated['topic'] ?? $meeting->topic,
            agenda: $validated['agenda'] ?? $meeting->agenda,
            created_at: $meeting->created_at,
            duration: $validated['duration'] ?? $meeting->duration,
            start_time: $validated['start_time'] ?? $meeting->start_time,
            start_url: $meeting->start_url,
            join_url: $meeting->join_url,
            status: $meeting->status,
            timezone: $validated['timezone'] ?? $meeting->timezone,
            password: $validated['password'] ?? $meeting->password,
            join_before_host: $validated['join_before_host'] ?? $meeting->join_before_host,
            tracking_fields: $meeting->tracking_fields,
        );
    }
}
