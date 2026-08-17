<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Enums\ApiScope;
use App\Http\Controllers\Api\ApiController;
use Dedoc\Scramble\Attributes\Endpoint;
use Illuminate\Http\JsonResponse;

class ApiScopeController extends ApiController
{
    /**
     * List all available API scopes
     *
     * Returns all available API scopes with their labels and descriptions.
     * This endpoint is public and does not require authentication.
     */
    #[Endpoint(operationId: 'apiTokens.scopes')]
    public function index(): JsonResponse
    {
        return $this->respondWithData(ApiScope::toArray());
    }
}
