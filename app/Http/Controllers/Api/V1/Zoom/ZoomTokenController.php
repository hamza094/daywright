<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Zoom;

use App\Actions\ZoomAction;
use App\Http\Controllers\Api\ApiController;
use App\Http\Requests\Api\V1\Zoom\JwtTokenRequest;
use App\Interfaces\Zoom;
use Dedoc\Scramble\Attributes\ExcludeAllRoutesFromDocs;
use Illuminate\Http\JsonResponse;

#[ExcludeAllRoutesFromDocs]
class ZoomTokenController extends ApiController
{
    public function getUserToken(Zoom $zoom): JsonResponse
    {
        $token = $zoom->getZakToken($this->authenticatedUser());

        return response()->json(['zak_token' => $token]);
    }

    public function getJwtToken(JwtTokenRequest $request, ZoomAction $action): JsonResponse
    {
        $role = $request->role;

        $meetingId = $request->meetingId;

        $token = $action->handle($meetingId, $role);

        return response()->json(['jwt_token' => $token]);
    }
}
