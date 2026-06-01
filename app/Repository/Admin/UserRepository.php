<?php

declare(strict_types=1);

namespace App\Repository\Admin;

use App\Http\Requests\Api\V1\Admin\UserFilterRequest;
use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Spatie\QueryBuilder\QueryBuilder;

class UserRepository
{
    /**
     * @return LengthAwarePaginator<int, User>
     */
    public function getUsersWithFilters(UserFilterRequest $request): LengthAwarePaginator
    {
        return QueryBuilder::for(
            User::query()
                ->with([
                    'subscriptions',
                    'adminGrantedBy:id,name',
                    'adminRevokedBy:id,name',
                ])
                ->withCount('projects')
                ->withCount('activeMembers as projects_member_count'),
            $request,
        )
            ->allowedFilters(...UserFilterRequest::allowedFilters())
            ->allowedSorts(...UserFilterRequest::allowedSorts())
            ->defaultSort(...UserFilterRequest::defaultSorts())
            ->paginate($request->perPage());
    }
}
