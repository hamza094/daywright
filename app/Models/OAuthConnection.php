<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class OAuthConnection extends Model
{
    protected $table = 'oauth_connections';

    protected $guarded = [];

    protected $casts = [
        'access_token' => 'encrypted',
        'refresh_token' => 'encrypted',
        'expires_at' => 'datetime',
        'scopes' => 'json',
        'metadata' => 'json',
    ];

    /**
     * @return BelongsTo<User, OAuthConnection>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
