<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\DataTransferObjects\Meeting\MeetingNotificationData;
use App\Enums\MeetingState;
use App\Jobs\SendMeetingEndedNotification;
use App\Jobs\SendMeetingStartedNotification;
use App\Models\Meeting;
use Exception;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Log;

final class CheckUnsentMeetingNotifications extends Command
{
    protected $signature = 'meetings:check-unsent-notifications';

    protected $description = 'Check for stuck meeting notifications and re-dispatch jobs';

    public function handle(): int
    {
        $this->checkStuckStartedNotifications();
        $this->checkStuckEndedNotifications();

        $this->info('Meeting notification check completed.');

        return 0;
    }

    private function checkStuckStartedNotifications(): void
    {
        $this->staleNotificationMeetings(
            pendingColumn: 'started_notification_pending_at',
            sentColumn: 'started_notification_sent_at',
            status: MeetingState::START,
        )
            ->chunkById(50, fn (\Illuminate\Support\Collection $meetings) => $this->redispatchStartedNotifications($meetings));
    }

    private function checkStuckEndedNotifications(): void
    {
        $this->staleNotificationMeetings(
            pendingColumn: 'ended_notification_pending_at',
            sentColumn: 'ended_notification_sent_at',
            status: MeetingState::ENDS,
        )
            ->chunkById(50, fn (\Illuminate\Support\Collection $meetings) => $this->redispatchEndedNotifications($meetings));
    }

    /**
     * @return Builder<Meeting>
     */
    private function staleNotificationMeetings(
        string $pendingColumn,
        string $sentColumn,
        MeetingState $status,
    ): Builder {
        $cutoff = now()->subMinutes(10);

        return Meeting::query()
            ->with(['project.user'])
            ->whereNull($sentColumn)
            ->where(function (Builder $query) use ($pendingColumn, $status, $cutoff): void {
                $query->where($pendingColumn, '<', $cutoff)
                    ->orWhere(function (Builder $fallback) use ($pendingColumn, $status, $cutoff): void {
                        $fallback
                            ->whereNull($pendingColumn)
                            ->where('status', $status->value)
                            ->where('updated_at', '<', $cutoff);
                    });
            })
            ->whereHas('project', function (Builder $query): void {
                $query->whereNull('deleted_at');
            });
    }

    /**
     * @param  \Illuminate\Database\Eloquent\Collection<int, Meeting>  $meetings
     */
    private function redispatchStartedNotifications(\Illuminate\Support\Collection $meetings): void
    {
        foreach ($meetings as $meeting) {
            try {
                $notificationData = MeetingNotificationData::fromArray([
                    'project_name' => $meeting->project->name,
                    'project_slug' => $meeting->project->slug,
                    'meeting_topic' => $meeting->topic,
                    'meeting_timezone' => $meeting->timezone,
                    'meeting_join_url' => $meeting->join_url,
                    'start_time' => $meeting->start_time,
                    'end_time' => null,
                    'notifier' => [
                        'name' => $meeting->project->user->name,
                        'email' => $meeting->project->user->email,
                    ],
                ]);

                SendMeetingStartedNotification::dispatch($meeting->id, $notificationData);

                Log::info('Re-dispatched stuck meeting started notification', [
                    'meeting_id' => $meeting->id,
                    'project_id' => $meeting->project_id,
                ]);
            } catch (Exception $e) {
                Log::error('Failed to re-dispatch stuck meeting started notification', [
                    'meeting_id' => $meeting->id,
                    'project_id' => $meeting->project_id,
                    'exception_class' => $e::class,
                    'exception_code' => $e->getCode(),
                ]);
            }
        }
    }

    /**
     * @param  \Illuminate\Database\Eloquent\Collection<int, Meeting>  $meetings
     */
    private function redispatchEndedNotifications(\Illuminate\Support\Collection $meetings): void
    {
        foreach ($meetings as $meeting) {
            try {
                $notificationData = MeetingNotificationData::fromArray([
                    'project_name' => $meeting->project->name,
                    'project_slug' => $meeting->project->slug,
                    'meeting_topic' => $meeting->topic,
                    'meeting_timezone' => $meeting->timezone,
                    'meeting_join_url' => null,
                    'start_time' => $meeting->start_time,
                    'end_time' => null,
                    'notifier' => [
                        'name' => $meeting->project->user->name,
                        'email' => $meeting->project->user->email,
                    ],
                ]);

                SendMeetingEndedNotification::dispatch($meeting->id, $notificationData);

                Log::info('Re-dispatched stuck meeting ended notification', [
                    'meeting_id' => $meeting->id,
                    'project_id' => $meeting->project_id,
                ]);
            } catch (Exception $e) {
                Log::error('Failed to re-dispatch stuck meeting ended notification', [
                    'meeting_id' => $meeting->id,
                    'project_id' => $meeting->project_id,
                    'exception_class' => $e::class,
                    'exception_code' => $e->getCode(),
                ]);
            }
        }
    }
}
