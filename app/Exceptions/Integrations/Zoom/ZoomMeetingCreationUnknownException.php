<?php

declare(strict_types=1);

namespace App\Exceptions\Integrations\Zoom;

use App\Exceptions\ApiException;
use Illuminate\Http\Request;
use Override;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

final class ZoomMeetingCreationUnknownException extends ApiException
{
    public function __construct(string $message = '', ?Throwable $previous = null)
    {
        parent::__construct($message, 0, $previous);
    }

    public function status(): int
    {
        return Response::HTTP_ACCEPTED;
    }

    public function errorCode(): string
    {
        return 'zoom_meeting_creation_unknown';
    }

    #[Override]
    public function publicMessage(): string
    {
        return 'Meeting creation is being processed. The meeting status will be updated shortly.';
    }

    #[Override]
    public function meta(Request $request): array
    {
        return [
            'provider' => 'zoom',
            'reason' => 'creation_result_uncertain',
        ];
    }
}
