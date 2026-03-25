<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Zoom;

use App\Actions\ZoomAction;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Zoom\JwtTokenRequest;
use App\Interfaces\Zoom;
use Auth;
use Dedoc\Scramble\Attributes\ExcludeAllRoutesFromDocs;
use Illuminate\Http\JsonResponse;

#[ExcludeAllRoutesFromDocs]
class ZoomTokenController extends Controller
{
    public function getUserToken(Zoom $zoom): JsonResponse
    {
        $token = $zoom->getZakToken(Auth::user());

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
