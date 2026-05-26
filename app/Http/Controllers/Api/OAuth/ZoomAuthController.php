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
    public function __construct(private readonly Zoom $zoom) {}

    public function redirect(): JsonResponse
    {
        $redirectDetails = $this->zoom->getAuthRedirectDetails();

        // Store the PKCE code verifier in cache keyed by the OAuth state so
        // stateless clients (mobile, SPA) that don't persist sessions can still
        // complete the callback flow.
        $cacheKey = 'oauth:zoom:'.$redirectDetails->state;
        cache()->put($cacheKey, $redirectDetails->codeVerifier, now()->addMinutes(10));

        return $this->respondWithData(['redirect_url' => $redirectDetails->authorizationUrl]);

    }

    public function callback(Request $request): JsonResponse
    {
        if ($request->string('error')->trim()->exactly('access_denied')) {
            abort(Response::HTTP_BAD_REQUEST, 'Zoom account connection denied');
        }

        $state = (string) $request->string('state')->trim();
        $cacheKey = 'oauth:zoom:'.$state;

        $hasRequiredFields = $request->filled(['code', 'state']) && cache()->has($cacheKey);

        if (! $hasRequiredFields) {
            abort(Response::HTTP_BAD_REQUEST, 'Missing required fields');
        }

        $callbackDetails = new AuthorizationCallbackDetails(
            authorizationCode: (string) $request->string('code')->trim(),
            expectedState: $state,
            state: $state,
            codeVerifier: cache()->pull($cacheKey),
        );

        $accessDetails = $this->zoom->authorize($callbackDetails);

        $this->authenticatedUser()->updateZoomOAuthDetails(
            accessToken: $accessDetails->accessToken,
            refreshToken: $accessDetails->refreshToken,
            expiresAt: $accessDetails->expiresAt,
        );

        return $this->respondWithMessage('Zoom account connected successfully');

    }
}
