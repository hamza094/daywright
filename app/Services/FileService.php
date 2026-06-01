<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\FileType;
use App\Models\User;
use Exception;
use Illuminate\Filesystem\FilesystemAdapter;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use InvalidArgumentException;
use Spatie\ImageOptimizer\OptimizerChainFactory;

use function Safe\parse_url;

class FileService
{
    // Stores a file in S3 and returns its URL (avatars) or storage key (private files)

    public function store(int|string $id, UploadedFile $file, string $fileType): string
    {
        // Reject invalid or empty uploads early to avoid attempting remote
        // storage operations (which require external config like S3 region).
        if (! $file->isValid() || $file->getSize() === 0) {
            throw ValidationException::withMessages([
                'file' => 'Invalid file upload',
            ]);
        }

        $folderName = $this->getFolderName($fileType);
        $fileName = $this->getGeneratedFileName($id, $file);

        // If the file is an image, optimize it before uploading
        $this->optimizeFile($file);

        return $this->uploadFileToS3($folderName, $file, $fileName, $fileType);

    }

    /**
     * Deletes the user's avatar file from S3 and clears the avatar_path.
     * Handles both int and string (UUID) user IDs.
     *
     * @throws Exception
     */
    public function deleteFile(User $user): void
    {
        DB::transaction(function () use ($user): void {
            $lockedUser = $this->lockUser($user);
            $avatarUrl = $lockedUser->avatar_path ?? $lockedUser->avatar;
            $filePath = $this->extractStoragePath($avatarUrl);

            $lockedUser->update(['avatar_path' => null]);

            if ($filePath === null) {
                return;
            }

            DB::afterCommit(function () use ($filePath): void {
                try {
                    $this->disk()->delete($filePath);
                } catch (Exception $exception) {
                    report($exception);
                }
            });
        });
    }

    /**
     * Explicit helper to delete the avatar file for a given user.
     *
     * Kept as a thin wrapper for backward compatibility.
     */
    public function deleteAvatar(User $user): void
    {
        $this->deleteFile($user);
    }

    /* Returns the appropriate folder name for the file type. */
    private function getFolderName(string $fileType): string
    {
        return match ($fileType) {
            FileType::CONVERSATION => 'conversations',
            FileType::AVATAR => 'avatars',
            default => throw new InvalidArgumentException('Invalid file type'),
        };
    }

    /* Generates a unique file name for the file using the project id and file hash. */
    private function getGeneratedFileName(int|string $id, UploadedFile $file): string
    {
        return $id.'_'.$file->hashName();
    }

    private function optimizeFile(UploadedFile $file): void
    {
        if (str_starts_with((string) $file->getMimeType(), 'image/')) {
            OptimizerChainFactory::create()->optimize($file->path());
        }
    }

    private function uploadFileToS3(string $folderName, UploadedFile $file, string $fileName, string $fileType): string
    {
        $disk = $this->disk();
        $visibility = $fileType === FileType::AVATAR ? 'public' : 'private';

        try {
            $path = $disk->putFileAs($folderName, $file, $fileName, $visibility);
        } catch (Exception $e) {
            throw ValidationException::withMessages(['File upload failed: '.$e->getMessage()]);
        }

        if ($fileType === FileType::AVATAR) {
            return $disk->url($path);
        }

        return $path;
    }

    private function disk(): FilesystemAdapter
    {
        return Storage::disk('s3');
    }

    private function lockUser(User $user): User
    {
        return User::query()
            ->whereKey($user->getKey())
            ->lockForUpdate()
            ->firstOrFail();
    }

    private function extractStoragePath(?string $url): ?string
    {
        if (! $url) {
            return null;
        }

        if (! str_starts_with($url, 'http')) {
            return ltrim($url, '/');
        }

        $path = parse_url($url, PHP_URL_PATH) ?: '';
        if ($path === '' || $path === '0') {
            $path = Str::after($url, '.com/');
        }

        $path = ltrim($path, '/');

        return $path === '' ? null : $path;
    }
}
