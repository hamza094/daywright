<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\OAuth;

use App\DataTransferObjects\Zoom\AuthorizationCallbackDetails;
use App\Http\Controllers\Api\ApiController;
use App\Interfaces\Zoom;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

final class ZoomAuthController extends ApiController
{
    public function redirect(Request $request): JsonResponse
    {
        $redirectDetails = app(Zoom::class)
            ->getAuthRedirectDetails();

        session()->put('oauth_zoom_state', $redirectDetails->state);

        session()->put('oauth_zoom_code_verifier', $redirectDetails->codeVerifier);

        return $this->respondWithData(['redirect_url' => $redirectDetails->authorizationUrl]);

    }

    public function callback(Request $request): JsonResponse
    {
        if ($request->string('error')->trim()->exactly('access_denied')) {
            abort(Response::HTTP_BAD_REQUEST, 'Zoom account connection denied');
        }

        $hasRequiredFields = $request->filled(['code', 'state'])
          && session()->has('oauth_zoom_state')
          && session()->has('oauth_zoom_code_verifier');

        if (! $hasRequiredFields) {
            abort(Response::HTTP_BAD_REQUEST, 'Missing required fields');
        }

        $callbackDetails = new AuthorizationCallbackDetails(
            authorizationCode: (string) $request->string('code')->trim(),
            expectedState: session()->pull('oauth_zoom_state'),
            state: (string) $request->string('state')->trim(),
            codeVerifier: session()->pull('oauth_zoom_code_verifier'),
        );

        $accessDetails = app(Zoom::class)->authorize($callbackDetails);

        auth()->user()->updateZoomOAuthDetails(
            accessToken: $accessDetails->accessToken,
            refreshToken: $accessDetails->refreshToken,
            expiresAt: $accessDetails->expiresAt,
        );

        return $this->respondWithMessage('Zoom account connected successfully');

    }
}
