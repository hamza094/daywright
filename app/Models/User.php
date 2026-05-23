<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\OAuthProvider;
use App\Jobs\QueuedPasswordResetJob;
use App\Jobs\QueuedVerifyEmailJob;
use App\Traits\HasAdminAccess;
use App\Traits\HasSubscription;
use Database\Factories\UserFactory;
use DateTimeImmutable;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
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
use Override;

/** @use HasFactory<UserFactory> */
class User extends Authenticatable implements MustVerifyEmail, TwoFactorAuthenticatable
{
    use Billable, HasAdminAccess, HasApiTokens, HasFactory, HasSubscription, Notifiable, SoftDeletes, TwoFactorAuthentication;

    protected $guarded = [];

    /**
     * The attributes that should be hidden for arrays.
     *
     * @var list<string>
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
        'admin_granted_at' => 'datetime',
        'admin_revoked_at' => 'datetime',
        'email_verified_at' => 'datetime',
        'is_admin' => 'boolean',
        'oauth_provider' => OAuthProvider::class,
        'oauth_token' => 'encrypted',
        'oauth_refresh_token' => 'encrypted',
        'zoom_access_token' => 'encrypted',
        'zoom_refresh_token' => 'encrypted',
        'zoom_expires_at' => 'datetime',
    ];

    #[Override]
    public function getRouteKeyName(): string
    {
        return 'uuid';
    }

    #[Override]
    public function sendEmailVerificationNotification(): void
    {
        // dispactches the job to the queue passing it this User object
        QueuedVerifyEmailJob::dispatch($this);
    }

    #[Override]
    public function sendPasswordResetNotification($token): void
    {
        // dispactches the job to the queue passing it this User object
        QueuedPasswordResetJob::dispatch($this, $token);
    }

    /**
     * Get projects created by user.
     *
     * @return HasMany<Project, User>
     *
     * @phpstan-return HasMany<Project, static>
     */
    public function projects(): HasMany
    {
        // @phpstan-ignore-next-line - relation returned has `$this` declaring model; suppress template covariance false-positive
        return $this->hasMany(Project::class);
    }

    public function activities()
    {
        return $this->hasMany(Activity::class);
    }

    /**
     * Get all conversation associated by user
     *
     * @return HasMany<Conversation, User>
     *
     * @phpstan-return HasMany<Conversation, static>
     */
    public function conversations(): HasMany
    {
        // @phpstan-ignore-next-line - relation returned has `$this` declaring model; suppress template covariance false-positive
        return $this->hasMany(Conversation::class);
    }

    /**
     * Get user profile information
     *
     * @return HasOne<UserInfo, User>
     *
     * @phpstan-return HasOne<UserInfo, static>
     */
    public function info(): HasOne
    {
        // @phpstan-ignore-next-line - relation returned has `$this` declaring model; suppress template covariance false-positive
        return $this->hasOne(UserInfo::class);
    }

    /**
     * Get the user who granted admin access.
     *
     * @return BelongsTo<User, User>
     *
     * @phpstan-return BelongsTo<User, static>
     */
    public function adminGrantedBy(): BelongsTo
    {
        // @phpstan-ignore-next-line - relation returned has `$this` declaring model; suppress template covariance false-positive
        return $this->belongsTo(self::class, 'admin_granted_by');
    }

    /**
     * Get the user who revoked admin access.
     *
     * @return BelongsTo<User, User>
     *
     * @phpstan-return BelongsTo<User, static>
     */
    public function adminRevokedBy(): BelongsTo
    {
        // @phpstan-ignore-next-line - relation returned has `$this` declaring model; suppress template covariance false-positive
        return $this->belongsTo(self::class, 'admin_revoked_by');
    }

    /**
     * Get projects which user is member of.
     *
     * @return BelongsToMany<Project, User, \Illuminate\Database\Eloquent\Relations\Pivot>
     *
     * @phpstan-return BelongsToMany<Project, static, \Illuminate\Database\Eloquent\Relations\Pivot>
     */
    public function members(?bool $active = null): BelongsToMany
    {
        if ($active !== null) {
            // @phpstan-ignore-next-line - relation returned has `$this` declaring model; suppress template covariance false-positive
            return $this->belongsToMany(Project::class, 'project_members')
                ->withTimestamps()
                ->wherePivot('active', $active);
        }

        // @phpstan-ignore-next-line - relation returned has `$this` declaring model; suppress template covariance false-positive
        return $this->belongsToMany(Project::class, 'project_members')
            ->withTimestamps();
    }

    /**
     * Get projects where the user is an active member.
     *
     * @return BelongsToMany<Project, User, \Illuminate\Database\Eloquent\Relations\Pivot>
     *
     * @phpstan-return BelongsToMany<Project, static, \Illuminate\Database\Eloquent\Relations\Pivot>
     */
    public function activeMembers(): BelongsToMany
    {
        return $this->members(true);
    }

    /**
     * Get projects where the user is an inactive member.
     *
     * @return BelongsToMany<Project, User, \Illuminate\Database\Eloquent\Relations\Pivot>
     *
     * @phpstan-return BelongsToMany<Project, static, \Illuminate\Database\Eloquent\Relations\Pivot>
     */
    public function inactiveMembers(): BelongsToMany
    {
        return $this->members(false);
    }

    public function getAvatarAttribute(): string|bool
    {
        return $this->avatar_path ?: false;
    }

    /**
     * Get all messages created by user
     *
     * @return BelongsToMany<Message, User, \Illuminate\Database\Eloquent\Relations\Pivot>
     *
     * @phpstan-return BelongsToMany<Message, static, \Illuminate\Database\Eloquent\Relations\Pivot>
     */
    public function messages(): BelongsToMany
    {
        // @phpstan-ignore-next-line - relation returned has `$this` declaring model; suppress template covariance false-positive
        return $this->belongsToMany(Message::class);
    }

    /**
     * Get all tasks created by the user
     *
     * @return HasMany<Task, User>
     *
     * @phpstan-return HasMany<Task, static>
     */
    public function tasks(): HasMany
    {
        // @phpstan-ignore-next-line - relation returned has `$this` declaring model; suppress template covariance false-positive
        return $this->hasMany(Task::class);
    }

    /**
     * Get tasks assigned to user
     *
     * @return BelongsToMany<Task, User, \Illuminate\Database\Eloquent\Relations\Pivot>
     *
     * @phpstan-return BelongsToMany<Task, static, \Illuminate\Database\Eloquent\Relations\Pivot>
     */
    public function assigned(): BelongsToMany
    {
        // @phpstan-ignore-next-line - relation returned has `$this` declaring model; suppress template covariance false-positive
        return $this->belongsToMany(Task::class);
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
        return (bool) ($this->zoom_access_token
            && $this->zoom_refresh_token
            && $this->zoom_expires_at);
    }

    /**
     * Get meetings created by user.
     *
     * @return HasMany<Meeting, User>
     *
     * @phpstan-return HasMany<Meeting, static>
     */
    public function meetings(): HasMany
    {
        // @phpstan-ignore-next-line - relation returned has `$this` declaring model; suppress template covariance false-positive
        return $this->hasMany(Meeting::class);
    }

    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    #[Override]
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
