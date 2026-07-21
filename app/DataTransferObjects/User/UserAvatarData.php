<?php

declare(strict_types=1);

namespace App\DataTransferObjects\User;

use Illuminate\Http\UploadedFile;

final readonly class UserAvatarData
{
    public function __construct(
        public UploadedFile $avatar,
    ) {}
}
