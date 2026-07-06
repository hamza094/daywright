<?php

declare(strict_types=1);

namespace App\Models;

use App\Traits\RecordActivity;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

/**
 * @property-read Project $project
 */
class Message extends Model
{
    use HasFactory, RecordActivity;

    protected $casts = ['delivered_at' => 'datetime'];

    protected $guarded = [];

    protected static $recordableEvents = ['created'];

    /**
     * @return BelongsTo<Project, Message>
     *
     * @phpstan-return BelongsTo<Project, static>
     */
    public function project(): BelongsTo
    {
        // @phpstan-ignore-next-line - relation returned has `$this` declaring model; suppress template covariance false-positive
        return $this->belongsTo(Project::class);
    }

    /**
     * Get the users associated to message.
     *
     * @return BelongsToMany<User, Message, \Illuminate\Database\Eloquent\Relations\Pivot>
     *
     * @phpstan-return BelongsToMany<User, static, \Illuminate\Database\Eloquent\Relations\Pivot>
     */
    public function users(): BelongsToMany
    {
        // @phpstan-ignore-next-line - relation returned has `$this` declaring model; suppress template covariance false-positive
        return $this->belongsToMany(User::class);
    }

    public function scopeMessageScheduled($query): void
    {
        $query
            ->where('delivered', false)
            ->whereNull('batch_id')
            ->whereNotNull('delivered_at')
            ->where('delivered_at', '<=', Carbon::now());
    }
}
