<?php

declare(strict_types=1);

namespace App\Services\Project;

use App\Enums\Subscription\PlanLimitType;
use App\Interfaces\Zoom;
use App\Models\Meeting;
use App\Models\Project;
use App\Models\User;
use App\Services\Subscription\PlanLimitService;
use Closure;
use Illuminate\Contracts\Cache\LockTimeoutException;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;

class MeetingService
{
    private const int TRANSACTION_RETRY_ATTEMPTS = 5;

    private const int OPERATION_LOCK_SECONDS = 120;

    private const int OPERATION_LOCK_WAIT_SECONDS = 10;

    private const array MEETING_RESOURCE_RELATIONS = ['user'];

    public function __construct(private readonly PlanLimitService $planLimitService) {}

    /**
     * @return LengthAwarePaginator<int, Meeting>
     */
    public function getMeetingsData(Project $project, bool $isPrevious, int $perPage = 3, ?int $page = null): LengthAwarePaginator
    {
        $meetingsQuery = $project->meetings()->with(self::MEETING_RESOURCE_RELATIONS);

        $meetingsQuery->when($isPrevious, fn ($query) => $query->previous(), fn ($query) => $query->scheduled());

        return $meetingsQuery->paginate($perPage, ['*'], 'page', $page);
    }

    /**
     * @param  array<string, mixed>  $validated
     */
    public function createMeetingForProject(Project $project, User $user, array $validated, Zoom $zoom): Meeting
    {
        /** @var Meeting $projectMeeting */
        $projectMeeting = $this->executeWithLock(
            key: $this->meetingCreationLockKey($user),
            conflictMessage: 'A meeting is already being created for this account. Please retry.',
            callback: function () use ($project, $user, $validated, $zoom): Meeting {
                $lockedUser = $this->assertCanCreateMeeting($user);
                $meeting = $zoom->createMeeting($validated, $lockedUser);
                $meetingArray = (array) $meeting + ['user_id' => $lockedUser->id];

                /** @var Meeting $projectMeeting */
                $projectMeeting = DB::transaction(
                    fn (): Meeting => $project->meetings()->create($meetingArray),
                    attempts: self::TRANSACTION_RETRY_ATTEMPTS,
                );

                return $projectMeeting;
            },
        );

        return $this->loadForResponse($projectMeeting);
    }

    /**
     * @param  array<string, mixed>  $validated
     */
    public function updateProjectMeeting(Meeting $meeting, User $user, array $validated, Zoom $zoom): Meeting
    {
        /** @var Meeting $updatedMeeting */
        $updatedMeeting = $this->executeWithLock(
            key: $this->meetingLockKey($meeting),
            conflictMessage: 'This meeting is currently being updated. Please retry.',
            callback: function () use ($zoom, $meeting, $validated, $user): Meeting {
                $currentMeeting = $this->findMeetingOrFail($meeting);
                $localAttributes = Arr::except($validated, ['meeting_id']);

                $zoom->updateMeeting($localAttributes + ['meeting_id' => $currentMeeting->meeting_id], $user);

                /** @var Meeting $updatedMeeting */
                $updatedMeeting = DB::transaction(function () use ($currentMeeting, $localAttributes): Meeting {
                    $lockedMeeting = $this->lockMeeting($currentMeeting);
                    $lockedMeeting->update($localAttributes);

                    return $lockedMeeting;
                }, attempts: self::TRANSACTION_RETRY_ATTEMPTS);

                return $updatedMeeting;
            },
        );

        return $this->loadForResponse($updatedMeeting);
    }

    public function loadForResponse(Meeting $meeting): Meeting
    {
        $meeting->loadMissing(self::MEETING_RESOURCE_RELATIONS);

        return $meeting;
    }

    public function deleteProjectMeeting(Meeting $meeting, User $user, Zoom $zoom): void
    {
        $this->executeWithLock(
            key: $this->meetingLockKey($meeting),
            conflictMessage: 'This meeting is currently being deleted. Please retry.',
            callback: function () use ($zoom, $meeting, $user): void {
                $currentMeeting = $this->findMeetingOrFail($meeting);

                $zoom->deleteMeeting($currentMeeting->meeting_id, $user);

                DB::transaction(function () use ($currentMeeting): void {
                    $lockedMeeting = $this->lockMeeting($currentMeeting);
                    $lockedMeeting->delete();
                }, attempts: self::TRANSACTION_RETRY_ATTEMPTS);
            },
        );
    }

    private function assertCanCreateMeeting(User $user): User
    {
        /** @var User $lockedUser */
        $lockedUser = $this->planLimitService->executeWithinAccountLimit(
            PlanLimitType::CreatedMeetings,
            $user,
            fn (User $lockedUser): User => $lockedUser,
        );

        return $lockedUser;
    }

    /**
     * @template TReturn
     *
     * @param  Closure(): TReturn  $callback
     * @return TReturn
     */
    private function executeWithLock(string $key, string $conflictMessage, Closure $callback): mixed
    {
        try {
            return Cache::lock($key, self::OPERATION_LOCK_SECONDS)
                ->block(self::OPERATION_LOCK_WAIT_SECONDS, fn (): mixed => $callback());
        } catch (LockTimeoutException $exception) {
            throw new ConflictHttpException($conflictMessage, $exception);
        }
    }

    private function meetingCreationLockKey(User $user): string
    {
        return "meeting-create:user:{$user->getKey()}";
    }

    private function meetingLockKey(Meeting $meeting): string
    {
        return "meeting:{$meeting->getKey()}";
    }

    private function findMeetingOrFail(Meeting $meeting): Meeting
    {
        /** @var Meeting $currentMeeting */
        $currentMeeting = Meeting::query()
            ->whereKey($meeting->getKey())
            ->firstOrFail();

        return $currentMeeting;
    }

    private function lockMeeting(Meeting $meeting): Meeting
    {
        /** @var Meeting $lockedMeeting */
        $lockedMeeting = Meeting::query()
            ->whereKey($meeting->getKey())
            ->lockForUpdate()
            ->firstOrFail();

        return $lockedMeeting;
    }
}
