<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\OAuth;

use App\DataTransferObjects\OAuth\OAuthTokens;
use App\DataTransferObjects\Zoom\AuthorizationCallbackDetails;
use App\Http\Controllers\Api\ApiController;
use App\Repository\OAuthConnectionRepository;
use App\Services\Zoom\ZoomAuthorizationStateStore;
use App\Services\Zoom\ZoomOAuthService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

final class ZoomAuthController extends ApiController
{
    public function __construct(
        private readonly ZoomOAuthService $zoomOAuth,
        private readonly ZoomAuthorizationStateStore $authorizationStateStore,
        private readonly OAuthConnectionRepository $oauthRepository,
    ) {}

    public function redirect(): JsonResponse
    {
        $redirectDetails = $this->zoomOAuth->getAuthRedirectDetails();

        $this->authorizationStateStore->storeRedirectDetails($redirectDetails);

        return $this->respondWithData(['redirect_url' => $redirectDetails->authorizationUrl]);
    }

    public function callback(Request $request): JsonResponse
    {
        if ($request->string('error')->trim()->exactly('access_denied')) {
            abort(Response::HTTP_BAD_REQUEST, 'Zoom account connection denied');
        }

        $accessDetails = $this->zoomOAuth->authorize($this->callbackDetails($request));

        $this->oauthRepository->saveTokens(
            $this->authenticatedUser(),
            'zoom',
            new OAuthTokens(
                accessToken: $accessDetails->accessToken,
                refreshToken: $accessDetails->refreshToken,
                expiresAt: $accessDetails->expiresAt,
            ),
        );

        return $this->respondWithMessage('Zoom account connected successfully');
    }

    private function callbackDetails(Request $request): AuthorizationCallbackDetails
    {
        $authorizationCode = (string) $request->string('code')->trim();
        $state = (string) $request->string('state')->trim();

        if ($authorizationCode === '' || $state === '') {
            abort(Response::HTTP_BAD_REQUEST, 'Missing required fields');
        }

        return new AuthorizationCallbackDetails(
            authorizationCode: $authorizationCode,
            expectedState: $state,
            state: $state,
            codeVerifier: $this->authorizationStateStore->takeVerifier($state),
        );
    }
}
