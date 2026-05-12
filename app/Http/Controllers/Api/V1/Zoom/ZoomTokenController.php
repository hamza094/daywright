<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Zoom;

use App\Actions\ZoomAction;
use App\Http\Controllers\Api\ApiController;
use App\Http\Requests\Api\V1\Zoom\JwtTokenRequest;
use App\Interfaces\Zoom;
use Dedoc\Scramble\Attributes\ExcludeAllRoutesFromDocs;
use Illuminate\Http\JsonResponse;
use Symfony\Component\HttpFoundation\Response;

#[ExcludeAllRoutesFromDocs]
class ZoomTokenController extends ApiController
{
    public function getUserToken(Zoom $zoom): JsonResponse
    {
        $token = $zoom->getZakToken($this->authenticatedUser());

        return $this->respondWithData(['zak_token' => $token], Response::HTTP_OK);
    }

    public function getJwtToken(JwtTokenRequest $request, ZoomAction $action): JsonResponse
    {
        $role = (int) $request->integer('role');

        $meetingId = $request->integer('meetingId');

        $token = $action->handle($meetingId, $role);

        return $this->respondWithData(['jwt_token' => $token], Response::HTTP_OK);
    }
}
