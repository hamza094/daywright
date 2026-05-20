<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Api\ApiController;
use App\Http\Requests\Api\V1\Admin\UpdateUserRoleRequest;
use App\Http\Requests\Api\V1\Admin\UserFilterRequest;
use App\Http\Resources\Api\V1\Admin\User\AdminUserResource;
use App\Models\User;
use App\Repository\Admin\UserRepository;
use App\Services\Admin\AdminAccessService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class UserController extends ApiController
{
    public function __construct(
        private readonly AdminAccessService $adminAccessService,
        private readonly UserRepository $userRepository,
    ) {}

    public function index(UserFilterRequest $request): AnonymousResourceCollection
    {
        $users = $this->userRepository->getUsersWithFilters($request);

        return AdminUserResource::collection($users);
    }

    public function updateRole(UpdateUserRoleRequest $request, User $user): JsonResponse
    {
        if ($request->boolean('is_admin')) {
            $this->adminAccessService->grantAdminAccess($user, $request->user());

            return $this->respondUpdated(new AdminUserResource($user->fresh([
                'adminGrantedBy:id,name',
                'adminRevokedBy:id,name',
            ])));
        }

        $this->adminAccessService->revokeAdminAccess($user, $request->user());

        return $this->respondUpdated(new AdminUserResource($user->fresh([
            'adminGrantedBy:id,name',
            'adminRevokedBy:id,name',
        ])));
    }
}
