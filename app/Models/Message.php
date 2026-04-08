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
     * @phpstan-return BelongsTo<Project, $this>
     */
    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    /**
     * Get the users associated to message.
     *
     * @return BelongsToMany<User>
     */
    public function users(): BelongsToMany
    {
        return $this->belongsToMany(User::class);
    }

    public function scopeMessageScheduled($query): void
    {
        $query
            ->where('delivered', false)
            ->whereNotNull('delivered_at')
            ->whereDate('delivered_at', '<=', Carbon::now());
    }
}
