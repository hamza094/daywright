<?php

declare(strict_types=1);

namespace App\Actions\Webhooks\Zoom;

use App\DataTransferObjects\Zoom\MeetingCreatedWebhookData;
use App\Enums\Meeting\MeetingSyncStatus;
use App\Models\Meeting;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

final readonly class HandleMeetingCreatedWebhook
{
    private const string OPERATION = 'zoom.webhook.meeting.created';

    public function handle(MeetingCreatedWebhookData $data): void
    {
        if ($data->creationSource !== 'open_api') {
            Log::info(self::OPERATION.' ignored - not from API', [
                'operation' => self::OPERATION,
                'meeting_id' => $data->meetingId,
                'request_id' => $data->requestId,
                'creation_source' => $data->creationSource,
            ]);

            return;
        }

        if ($data->operationId === null) {
            Log::info(self::OPERATION.' ignored - no operation tracking field', [
                'operation' => self::OPERATION,
                'meeting_id' => $data->meetingId,
                'request_id' => $data->requestId,
            ]);

            return;
        }

        DB::transaction(function () use ($data): void {
            $meeting = Meeting::query()
                ->where('sync_operation_id', $data->operationId)
                ->lockForUpdate()
                ->first();

            if (! $meeting instanceof Meeting) {
                return;
            }

            $this->attachRemoteMeetingId($meeting, $data);
        });
    }

    private function attachRemoteMeetingId(Meeting $meeting, MeetingCreatedWebhookData $data): void
    {
        if ($meeting->sync_status === MeetingSyncStatus::Active) {
            if ($this->sameMeetingId($meeting->meeting_id, $data->meetingId)) {
                return;
            }

            Log::warning(self::OPERATION.' conflict - different ID already stored', [
                'operation' => self::OPERATION,
                'local_meeting_id' => $meeting->id,
                'stored_meeting_id' => $meeting->meeting_id,
                'webhook_meeting_id' => $data->meetingId,
                'request_id' => $data->requestId,
            ]);

            return;
        }

        if (! in_array($meeting->sync_status, [MeetingSyncStatus::Creating, MeetingSyncStatus::CreateUnknown], true)) {
            return;
        }

        if ($meeting->meeting_id !== null) {
            Log::warning(self::OPERATION.' conflict - meeting ID already set', [
                'operation' => self::OPERATION,
                'local_meeting_id' => $meeting->id,
                'sync_status' => $meeting->sync_status->value,
                'current_meeting_id' => $meeting->meeting_id,
                'webhook_meeting_id' => $data->meetingId,
                'request_id' => $data->requestId,
            ]);

            return;
        }

        if ($meeting->sync_status === MeetingSyncStatus::Creating) {
            $meeting->transitionTo(MeetingSyncStatus::CreateUnknown, 'sync_status');
        }

        $meeting->update([
            'meeting_id' => $data->meetingId,
            'join_url' => $data->joinUrl,
            'sync_available_at' => now(),
            'sync_claim_token' => null,
            'sync_lease_expires_at' => null,
        ]);
    }

    private function sameMeetingId(int|string|null $first, int|string $second): bool
    {
        return $first !== null && (string) $first === (string) $second;
    }
}
