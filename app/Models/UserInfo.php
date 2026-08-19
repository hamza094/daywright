<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class UserInfo extends Model
{
    use HasFactory;

    protected $guarded = [];

    /**
     * The attributes that should be cast to native types.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'mobile' => 'string',
    ];

    /**
     * @var array<string>
     */
    protected $touches = ['user'];

    /**
     * Get the user who is associated to the userinfo.
     *
     * @return BelongsTo<User, UserInfo>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
