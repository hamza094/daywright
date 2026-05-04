<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Api\ApiController;
use App\Http\Requests\Api\V1\Admin\UpdateUserRoleRequest;
use App\Http\Requests\Api\V1\Admin\UserFilterRequest;
use App\Http\Resources\Api\V1\Admin\User\AdminUserResource;
use App\Models\User;
use App\Services\Admin\AdminAccessService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class UserController extends ApiController
{
    public function __construct(private readonly AdminAccessService $adminAccessService) {}

    public function index(UserFilterRequest $request): AnonymousResourceCollection
    {
        $search = $request->searchTerm();

        $users = User::with([
            'subscriptions',
            'adminGrantedBy:id,name',
            'adminRevokedBy:id,name',
        ])
            ->withCount('projects')
            ->withCount('activeMembers as projects_member_count')
            ->when($search, function ($query) use ($search): void {
                $query->where(function ($subQuery) use ($search): void {
                    $subQuery->where('name', 'like', '%'.$search.'%')
                        ->orWhere('username', 'like', '%'.$search.'%')
                        ->orWhere('email', 'like', '%'.$search.'%');
                });
            })
            ->paginate($request->perPage());

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
