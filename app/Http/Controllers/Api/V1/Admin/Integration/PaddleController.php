<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Admin\Integration;

use App\DataTransferObjects\Paddle\UserSubscriptionData;
use App\Http\Controllers\Api\ApiController;
use App\Interfaces\PaddleApi;
use Illuminate\Http\JsonResponse;

class PaddleController extends ApiController
{
    public function subscribedUsers(PaddleApi $paddle): JsonResponse
    {
        $data = $paddle->subscriptionUsersList(
            new UserSubscriptionData(
                vendorID: (int) config('services.paddle.vendor_id'),
                vendorAuthCode: config('services.paddle.vendor_auth_code'),
                resultsPerPage: config('services.paddle.results_per_page')
            )
        );

        return $this->respondWithData($data);
    }
}
