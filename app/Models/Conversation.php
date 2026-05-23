<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Override;
use Throwable;

use function Safe\parse_url;
use function Safe\preg_match_all;
use function Safe\preg_replace;

/**
 * @property string|null $file
 * @property-read string|null $file_url
 */
class Conversation extends Model
{
    use HasFactory;

    private const int FILE_URL_TTL_MINUTES = 5;

    protected $guarded = [];

    /**
     * Get the project associated with the conversation.
     *
     * @return BelongsTo<Project, Conversation>
     */
    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    /**
     * Get the user who sent the conversation.
     *
     * @return BelongsTo<User, Conversation>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function setMessageAttribute($message): void
    {
        $this->attributes['message'] = preg_replace(
            '/@([\w\-]+)/',
            '<a href="/user/$1/profile" target="_blank">$0</a>',
            (string) $message
        );
    }

    public function mentionedUsers(): array
    {
        $message = (string) ($this->message ?? '');
        if ($message === '') {
            return [];
        }

        // Extract usernames preceded by a non-word boundary to avoid matching emails (name@domain)
        // Allows letters, numbers, underscore, dot and hyphen in usernames
        preg_match_all('/(?<![\w])@([\w.-]+)/', $message, $matches);
        $usernames = $matches[1];

        // De-duplicate while preserving order
        $seen = [];
        $unique = [];
        foreach ($usernames as $name) {
            if (! isset($seen[$name])) {
                $seen[$name] = true;
                $unique[] = $name;
            }
        }

        return $unique;
    }

    public function mentionedUsersData(): Collection
    {
        return $this->mentionedUsers() === []
        ? collect()
        : User::whereIn('username', $this->mentionedUsers())
            ->select('id', 'uuid', 'name', 'username')
            ->get();
    }

    public function getFileUrlAttribute(): ?string
    {
        $file = (string) ($this->file ?? '');

        if ($file === '') {
            return null;
        }

        if (str_starts_with($file, 'http')) {
            return $file;
        }

        try {
            return Storage::disk('s3')->temporaryUrl(
                $file,
                now()->addMinutes(self::FILE_URL_TTL_MINUTES)
            );
        } catch (Throwable $e) {
            Log::warning('Failed generating conversation file URL', ['file' => $file, 'error' => $e->getMessage()]);

            return null;
        }
    }

    #[Override]
    protected static function booted(): void
    {
        static::deleted(function (self $conversation): void {
            $conversation->deleteFileIfExists($conversation->file);
        });
    }

    /**
     * Resolve the storage path for a file. If given a full HTTP URL, parse
     * and return the path portion without a leading slash. Returns an empty
     * string when the path cannot be determined.
     */
    private function resolveStoragePath(?string $filePath): string
    {
        if (! $filePath) {
            return '';
        }

        $path = $filePath;
        if (str_starts_with($filePath, 'http')) {
            try {
                $path = ltrim(parse_url($filePath, PHP_URL_PATH) ?: '', '/');
            } catch (Throwable $e) {
                Log::warning('Failed parsing file URL', ['file' => $filePath, 'error' => $e->getMessage()]);
                $path = '';
            }
        }

        return $path;
    }

    private function deleteFileIfExists(?string $filePath): void
    {
        $path = $this->resolveStoragePath($filePath);

        if ($path === '') {
            return;
        }

        try {
            Storage::disk('s3')->delete($path);
        } catch (Throwable $e) {
            Log::error('S3 file deletion error', ['file' => $filePath, 'error' => $e->getMessage()]);
        }
    }
}
