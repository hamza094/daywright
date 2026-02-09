<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\OAuthProvider;
use App\Jobs\QueuedPasswordResetJob;
use App\Jobs\QueuedVerifyEmailJob;
use App\Traits\HasSubscription;
use DateTimeImmutable;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Str;
use Laragear\TwoFactor\Contracts\TwoFactorAuthenticatable;
use Laragear\TwoFactor\TwoFactorAuthentication;
use Laravel\Paddle\Billable;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable implements MustVerifyEmail, TwoFactorAuthenticatable
{
    use Billable, HasApiTokens, HasFactory, HasSubscription, Notifiable, SoftDeletes, TwoFactorAuthentication;

    public const ADMIN_EMAIL = 'morar.devon@example.com';

    protected $guarded = [];

    /**
     * The attributes that should be hidden for arrays.
     *
     * @var array<int, string>
     */
    protected $hidden = [
        'password',
        'remember_token',
        'oauth_token',
        'oauth_refresh_token',
        'zoom_access_token',
        'zoom_refresh_token',
        'zoom_expires_at',
    ];

    /**
     * The attributes that should be cast to native types.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'email_verified_at' => 'datetime',
        'oauth_provider' => OAuthProvider::class,
        'oauth_token' => 'encrypted',
        'oauth_refresh_token' => 'encrypted',
        'zoom_access_token' => 'encrypted',
        'zoom_refresh_token' => 'encrypted',
        'zoom_expires_at' => 'datetime',
    ];

    public function getRouteKeyName(): string
    {
        return 'uuid';
    }

    public function sendEmailVerificationNotification(): void
    {
        // dispactches the job to the queue passing it this User object
        QueuedVerifyEmailJob::dispatch($this);
    }

    public function sendPasswordResetNotification($token): void
    {
        // dispactches the job to the queue passing it this User object
        QueuedPasswordResetJob::dispatch($this, $token);
    }

    public function path(): string
    {
        return "/api/v1/users/{$this->uuid}";
    }

    /**
     * Get projects created by user.
     *
     * @return HasMany<Project>
     */
    public function projects(): HasMany
    {
        return $this->hasMany(Project::class);
    }

    public function activities()
    {
        return $this->hasMany(Activity::class);
    }

    /**
     * Get all conversation associated by user
     *
     * @return HasMany<Conversation>
     */
    public function conversations(): HasMany
    {
        return $this->hasMany(Conversation::class);
    }

    /**
     * Get user profile information
     *
     * @return HasOne<UserInfo>
     */
    public function info(): HasOne
    {
        return $this->hasOne(UserInfo::class);
    }

    /**
     * Get projects which user is member of.
     *
     * @return BelongsToMany<Project>
     */
    public function members(bool $active = false): BelongsToMany
    {
        return $this->belongsToMany(Project::class, 'project_members')
            ->wherePivot('active', $active)
            ->withTimestamps();
    }

    public function getAvatarAttribute(): string|bool
    {
        return $this->avatar_path ?: false;
    }

    /**
     * Get all messages created by user
     *
     * @return BelongsToMany<Message>
     */
    public function messages(): BelongsToMany
    {
        return $this->belongsToMany(Message::class);
    }

    /**
     * Get all tasks created by the user
     *
     * @return HasMany<Task>
     */
    public function tasks(): HasMany
    {
        return $this->hasMany(Task::class);
    }

    /**
     * Get tasks assigned to user
     *
     * @return BelongsToMany<Task>
     */
    public function assigned(): BelongsToMany
    {
        return $this->belongsToMany(Task::class);
    }

    public function isAdmin(): bool
    {
        return $this->email === self::ADMIN_EMAIL;

    }

    /**
     * Mark this user as the admin used by tests and feature checks.
     */
    public function markAsAdmin(): void
    {
        $this->email = self::ADMIN_EMAIL;
        $this->save();
    }

    public function updateZoomOAuthDetails(
        string $accessToken,
        string $refreshToken,
        DateTimeImmutable $expiresAt
    ): void {
        $this->zoom_access_token = $accessToken;
        $this->zoom_refresh_token = $refreshToken;
        $this->zoom_expires_at = $expiresAt;
        $this->save();
    }

    public function isConnectedToZoom(): bool
    {
        return $this->zoom_access_token
        && $this->zoom_refresh_token
        && $this->zoom_expires_at;
    }

    /**
     * Get meetings created by user.
     *
     * @return HasMany<Meeting>
     */
    public function meetings(): HasMany
    {
        return $this->hasMany(Meeting::class);
    }

    /**
     * @return array<string, mixed>
     */
    public function getNotifierData(): array
    {
        return [
            'uuid' => $this->uuid,
            'name' => $this->name,
            'username' => $this->username,
            'avatar_path' => $this->avatar_path,
            'email' => $this->email,
        ];
    }

    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected static function boot()
    {
        parent::boot();

        static::creating(function ($user): void {
            $user->uuid = (string) Str::uuid();
        });

        static::deleting(function ($user): void {
            $user->projects()->delete();
        });

        static::forceDeleting(function ($user): void {
            $user->notifications()->delete();
        });
    }
}
