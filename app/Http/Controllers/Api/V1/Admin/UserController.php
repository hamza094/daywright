<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Http\Resources\Api\V1\Admin\UsersResource;
use App\Models\User;
use App\Services\Admin\AdminAccessService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class UserController extends Controller
{
    public function __construct(private readonly AdminAccessService $adminAccessService) {}

    public function index(Request $request): AnonymousResourceCollection
    {
        $perPage = 7;

        $users = User::with([
            'subscriptions',
            'adminGrantedBy:id,name',
            'adminRevokedBy:id,name',
        ])
            ->withCount('projects')
            ->withCount('activeMembers as projects_member_count')
            ->when($request->search, function ($query) use ($request): void {
                $query->where('name', 'like', '%'.$request->search.'%')
                    ->orWhere('username', 'like', '%'.$request->search.'%')
                    ->orWhere('email', 'like', '%'.$request->search.'%');
            })
            ->paginate($perPage);

        return UsersResource::collection($users);
    }

    public function grantAdminAccess(Request $request, User $user): JsonResponse
    {
        $this->adminAccessService->grantAdminAccess($user, $request->user());

        return response()->json([
            'message' => 'Admin access granted successfully.',
            'user' => new UsersResource($user->fresh([
                'adminGrantedBy:id,name',
                'adminRevokedBy:id,name',
            ])),
        ]);
    }

    public function revokeAdminAccess(Request $request, User $user): JsonResponse
    {
        $this->adminAccessService->revokeAdminAccess($user, $request->user());

        return response()->json([
            'message' => 'Admin access revoked successfully.',
            'user' => new UsersResource($user->fresh([
                'adminGrantedBy:id,name',
                'adminRevokedBy:id,name',
            ])),
        ]);
    }
}
