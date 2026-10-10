<?php

declare(strict_types=1);

namespace App\Exceptions\Integrations\Zoom;

use App\Exceptions\ApiException;
use Illuminate\Http\Request;
use Override;
use Symfony\Component\HttpFoundation\Response;

final class ZoomMeetingOperationUnknownException extends ApiException
{
    public function status(): int
    {
        return Response::HTTP_ACCEPTED;
    }

    public function errorCode(): string
    {
        return 'zoom_meeting_operation_unknown';
    }

    #[Override]
    public function publicMessage(): string
    {
        return 'Meeting operation is being processed. The meeting status will be updated shortly.';
    }

    #[Override]
    public function meta(Request $request): array
    {
        return [
            'provider' => 'zoom',
            'reason' => 'operation_result_uncertain',
        ];
    }
}
