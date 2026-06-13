<?php

declare(strict_types=1);

namespace App\Interfaces;

use App\DataTransferObjects\Zoom\AccessTokenDetails;
use App\DataTransferObjects\Zoom\AuthorizationCallbackDetails;
use App\DataTransferObjects\Zoom\AuthorizationRedirectDetails;
use App\DataTransferObjects\Zoom\Meeting;
use App\Models\User;

interface Zoom
{
    public function getAuthRedirectDetails(): AuthorizationRedirectDetails;

    public function authorize(
        AuthorizationCallbackDetails $callbackDetails
    ): AccessTokenDetails;

    /**
     * @param  array<string, mixed>  $validated
     */
    public function createMeeting(array $validated, User $user): Meeting;

    /**
     * @param  array<string, mixed>  $validated
     */
    public function updateMeeting(array $validated, User $user): void;

    public function deleteMeeting(int $meetingId, User $user): void;

    public function getZakToken(User $user): string;
}
