<?php

declare(strict_types=1);

namespace App\Services\Zoom;

use App\DataTransferObjects\Zoom\Meeting;
use App\DataTransferObjects\Zoom\MeetingSummary;
use Closure;
use PHPUnit\Framework\Assert;
use Throwable;

trait ZoomServiceFakeHelpers
{
    public function shouldFailWithException(Throwable $exception): self
    {
        $this->failureException = $exception;

        return $this;
    }

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

    public function meetingNotFound(): self
    {
        $this->meetingToFind = null;

        return $this;
    }

    /**
     * @param  list<MeetingSummary>  $meetings
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
}
