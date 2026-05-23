<?php

declare(strict_types=1);

namespace App\Traits;

// @phpstan-ignore-next-line - trait may be used dynamically in the application
trait ApiResponses
{
    public function successResponse(?string $message = null, $data = null, int $status = 200): JsonResponse
    {
        $response = [];

        if ($message) {
            $response['message'] = $message;
        }
        if ($data !== null) {
            $response['data'] = $data;
        }

        return response()->json($response, $status);
    }
}
