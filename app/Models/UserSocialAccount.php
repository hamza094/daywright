<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\OAuthProvider;
use Database\Factories\UserSocialAccountFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** @use HasFactory<UserSocialAccountFactory> */
final class UserSocialAccount extends Model
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
        'provider' => OAuthProvider::class,
        'access_token' => 'encrypted',
        'refresh_token' => 'encrypted',
        'token_expires_at' => 'datetime',
    ];

    /**
     * @return BelongsTo<User, UserSocialAccount>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
