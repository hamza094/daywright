<?php

declare(strict_types=1);

namespace App\Services\User;

use App\Enums\FileType;
use App\Models\User;
use App\Services\FileService;
use Illuminate\Http\UploadedFile;

final readonly class AvatarService
{
    public function __construct(private FileService $fileService) {}

    public function update(User $user, UploadedFile $avatar): void
    {
        $path = $this->fileService->store($user->uuid, $avatar, FileType::AVATAR);

        $user->update(['avatar_path' => $path]);
    }

    public function remove(User $user): bool
    {
        if (! $user->avatar) {
            return false;
        }

        $this->fileService->deleteAvatar($user);

        return true;
    }
}
