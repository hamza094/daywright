<?php

declare(strict_types=1);

namespace App\Interfaces;

use App\DataTransferObjects\OAuth\OAuthTokens;
use App\DataTransferObjects\Zoom\AuthorizationCallbackDetails;
use App\DataTransferObjects\Zoom\AuthorizationRedirectDetails;
use App\DataTransferObjects\Zoom\Meeting;
use App\DataTransferObjects\Zoom\MeetingSummary;
use App\Models\User;

interface Zoom
{
    public function getAuthRedirectDetails(): AuthorizationRedirectDetails;

    public function authorize(
        AuthorizationCallbackDetails $callbackDetails
    ): OAuthTokens;

    /**
     * @param  array<string, mixed>  $validated
     */
    public function createMeeting(array $validated, User $user, string $operationId): Meeting;

    /**
     * @param  array<string, mixed>  $validated
     */
    public function updateMeeting(array $validated, User $user): void;

    public function deleteMeeting(int $meetingId, User $user): void;

    public function getMeeting(int|string $meetingId, User $user): ?Meeting;

    /**
     * @return list<MeetingSummary>
     */
    public function listMeetings(User $user): array;

    public function getZakToken(User $user): string;
}
