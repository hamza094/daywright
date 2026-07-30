<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\Meeting\MeetingSyncStatus;
use App\Traits\HasStateMachine;
use App\Traits\RecordActivity;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Meeting extends Model
{
    use HasFactory, HasStateMachine, RecordActivity;

    protected $guarded = [];

    /**
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'join_url',
        'start_url',
    ];

    /**
     * @var array<string, string>
     */
    protected $casts = [
        'password' => 'encrypted',
        'join_url' => 'encrypted',
        'start_url' => 'encrypted',
        'start_time' => 'datetime',
        'sync_status' => MeetingSyncStatus::class,
        'synced_at' => 'datetime',
        'started_notification_sent_at' => 'datetime',
        'ended_notification_sent_at' => 'datetime',
    ];

    /**
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo<User, self>
     */
    public function user(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo<Project, self>
     */
    public function project(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    /**
     * @param  Builder<Meeting>  $query
     * @return Builder<Meeting>
     */
    public function scopePrevious(Builder $query): Builder
    {
        return $query->where('start_time', '<', Carbon::now());
    }

    /**
     * @param  Builder<Meeting>  $query
     * @return Builder<Meeting>
     */
    public function scopeScheduled(Builder $query): Builder
    {
        return $query->where('start_time', '>=', Carbon::now());
    }

    /**
     * @param  Builder<Meeting>  $query
     * @return Builder<Meeting>
     */
    public function scopeSynced(Builder $query): Builder
    {
        return $query->where('sync_status', MeetingSyncStatus::Active);
    }

    /**
     * @return array<string, list<string>>
     */
    protected function validTransitions(): array
    {
        return [
            MeetingSyncStatus::Pending->value => [
                MeetingSyncStatus::Active->value,
                MeetingSyncStatus::Failed->value,
            ],
            MeetingSyncStatus::Active->value => [
                MeetingSyncStatus::Updating->value,
                MeetingSyncStatus::Deleting->value,
                MeetingSyncStatus::Failed->value,
            ],
            MeetingSyncStatus::Updating->value => [
                MeetingSyncStatus::Active->value,
                MeetingSyncStatus::UpdateFailed->value,
            ],
            MeetingSyncStatus::UpdateFailed->value => [
                MeetingSyncStatus::Updating->value,
                MeetingSyncStatus::Active->value,
                MeetingSyncStatus::Deleting->value,
            ],
            MeetingSyncStatus::Deleting->value => [
                MeetingSyncStatus::Deleted->value,
                MeetingSyncStatus::DeleteFailed->value,
            ],
            MeetingSyncStatus::DeleteFailed->value => [
                MeetingSyncStatus::Deleting->value,
                MeetingSyncStatus::Active->value,
            ],
            MeetingSyncStatus::Failed->value => [
                MeetingSyncStatus::Active->value,
                MeetingSyncStatus::Pending->value,
            ],
            MeetingSyncStatus::Deleted->value => [],
        ];
    }
}
