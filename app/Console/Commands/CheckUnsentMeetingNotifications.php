<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Enums\MeetingState;
use App\Jobs\SendMeetingEndedNotification;
use App\Jobs\SendMeetingStartedNotification;
use App\Models\Meeting;
use Exception;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class CheckUnsentMeetingNotifications extends Command
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
        Meeting::query()
            ->where('status', MeetingState::START->value)
            ->whereNull('started_notification_sent_at')
            ->where('updated_at', '<', now()->subMinutes(10))
            ->whereHas('project', function ($query): void {
                $query->whereNull('deleted_at');
            })
            ->chunkById(50, fn (\Illuminate\Support\Collection $meetings) => $this->redispatchStartedNotifications($meetings));
    }

    private function checkStuckEndedNotifications(): void
    {
        Meeting::query()
            ->where('status', MeetingState::ENDS->value)
            ->whereNull('ended_notification_sent_at')
            ->where('updated_at', '<', now()->subMinutes(10))
            ->whereHas('project', function ($query): void {
                $query->whereNull('deleted_at');
            })
            ->chunkById(50, fn (\Illuminate\Support\Collection $meetings) => $this->redispatchEndedNotifications($meetings));
    }

    /**
     * @param  \Illuminate\Database\Eloquent\Collection<int, Meeting>  $meetings
     */
    private function redispatchStartedNotifications(\Illuminate\Support\Collection $meetings): void
    {
        foreach ($meetings as $meeting) {
            try {
                $notificationData = [
                    'project_name' => $meeting->project->name,
                    'project_slug' => $meeting->project->slug,
                    'meeting_topic' => $meeting->topic,
                    'meeting_timezone' => $meeting->timezone,
                    'meeting_join_url' => $meeting->join_url,
                    'start_time' => $meeting->start_time,
                    'notifier' => [
                        'name' => $meeting->project->user->name,
                        'email' => $meeting->project->user->email,
                    ],
                ];

                SendMeetingStartedNotification::dispatch($meeting->id, $notificationData);

                Log::info('Re-dispatched stuck meeting started notification', [
                    'meeting_id' => $meeting->id,
                    'project_id' => $meeting->project_id,
                ]);
            } catch (Exception $e) {
                Log::error('Failed to re-dispatch stuck meeting started notification', [
                    'meeting_id' => $meeting->id,
                    'project_id' => $meeting->project_id,
                    'error' => $e->getMessage(),
                    'trace' => $e->getTraceAsString(),
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
                $notificationData = [
                    'project_name' => $meeting->project->name,
                    'project_slug' => $meeting->project->slug,
                    'meeting_topic' => $meeting->topic,
                    'meeting_timezone' => $meeting->timezone,
                    'start_time' => $meeting->start_time,
                    'end_time' => $meeting->end_time,
                    'notifier' => [
                        'name' => $meeting->project->user->name,
                        'email' => $meeting->project->user->email,
                    ],
                ];

                SendMeetingEndedNotification::dispatch($meeting->id, $notificationData);

                Log::info('Re-dispatched stuck meeting ended notification', [
                    'meeting_id' => $meeting->id,
                    'project_id' => $meeting->project_id,
                ]);
            } catch (Exception $e) {
                Log::error('Failed to re-dispatch stuck meeting ended notification', [
                    'meeting_id' => $meeting->id,
                    'project_id' => $meeting->project_id,
                    'error' => $e->getMessage(),
                    'trace' => $e->getTraceAsString(),
                ]);
            }
        }
    }
}
