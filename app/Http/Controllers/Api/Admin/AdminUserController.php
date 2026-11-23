<?php

namespace App\Http\Controllers\Api\Admin;

use App\Actions\Admin\User\UpdateUserRoleAction;
use App\Http\Controllers\Api\BaseApiController;
use App\Http\Resources\UserResource;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AdminUserController extends BaseApiController
{
    public function index(Request $request): JsonResponse
    {
        $role = $request->query('role');
        $search = $request->query('search');
        $perPage = (int) $request->query('per_page', 20);

        $query = User::with(['profile', 'roles'])->latest();

        if (!empty($role)) {
            $query->where('role', $role);
        }

        if (!empty($search)) {
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('email', 'like', "%{$search}%");
            });
        }

        $users = $query->paginate($perPage);

        return $this->sendPaginated(UserResource::collection($users), 'Users retrieved');
    }

    public function show(User $user): JsonResponse
    {
        return $this->sendSuccess(
            new UserResource($user->load(['profile', 'roles'])),
            'User detail retrieved'
        );
    }

    public function updateRole(Request $request, User $user, UpdateUserRoleAction $action): JsonResponse
    {
        $validated = $request->validate([
            'role' => 'required|string|in:administrator,admin_ppb,admin_bipa,admin_ppp,finance,content_writer,teacher,student',
            'status' => 'nullable|boolean',
            'additional_roles' => 'nullable|array',
            'additional_roles.*' => 'string',
        ]);

        $updated = $action->execute(
            $user,
            $validated['role'],
            $validated['status'] ?? null,
            $validated['additional_roles'] ?? []
        );

        return $this->sendSuccess(new UserResource($updated), 'User role updated successfully');
    }
}
