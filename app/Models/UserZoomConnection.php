<?php

declare(strict_types=1);

namespace App\Models;

use Database\Factories\UserZoomConnectionFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** @use HasFactory<UserZoomConnectionFactory> */
final class UserZoomConnection extends Model
{
    use HasFactory;

    protected $guarded = [];

    /**
     * @var array<int, string>
     */
    protected $hidden = [
        'access_token',
        'refresh_token',
    ];

    /**
     * @var array<string, string>
     */
    protected $casts = [
        'access_token' => 'encrypted',
        'refresh_token' => 'encrypted',
        'expires_at' => 'datetime',
    ];

    /**
     * @return BelongsTo<User, UserZoomConnection>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function hasValidCredentials(): bool
    {
        return filled($this->access_token)
            && filled($this->refresh_token)
            && $this->expires_at !== null;
    }
}
