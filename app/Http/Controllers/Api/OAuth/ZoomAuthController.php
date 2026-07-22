<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\OAuth;

use App\DataTransferObjects\OAuth\OAuthTokens;
use App\DataTransferObjects\Zoom\AuthorizationCallbackDetails;
use App\Http\Controllers\Api\ApiController;
use App\Http\Requests\Api\V1\Zoom\ZoomAuthorizationCallbackRequest;
use App\Repository\OAuthConnectionRepository;
use App\Services\Zoom\ZoomAuthorizationStateStore;
use App\Services\Zoom\ZoomOAuthService;
use Illuminate\Http\JsonResponse;
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

        $this->authorizationStateStore->store(
            redirectDetails: $redirectDetails,
            user: $this->authenticatedUser(),
        );

        return $this->respondWithData(['redirect_url' => $redirectDetails->authorizationUrl]);
    }

    public function callback(ZoomAuthorizationCallbackRequest $request): JsonResponse
    {
        $data = $request->toDto();

        if ($data->error !== null) {
            $state = $data->state;

            if ($state !== '') {
                $this->authorizationStateStore->forget($state);
            }

            abort(
                Response::HTTP_BAD_REQUEST,
                $data->error === 'access_denied'
                    ? 'Zoom account connection denied.'
                    : 'Zoom authorization failed.',
            );
        }

        $codeVerifier = $this->authorizationStateStore->consume(
            state: $data->state,
            user: $this->authenticatedUser(),
        );

        $callbackDetails = new AuthorizationCallbackDetails(
            authorizationCode: $data->code ?? '',
            state: $data->state,
            codeVerifier: $codeVerifier,
        );

        $accessDetails = $this->zoomOAuth->authorize($callbackDetails);

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
}
