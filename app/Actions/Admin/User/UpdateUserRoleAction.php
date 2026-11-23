<?php

namespace App\Actions\Admin\User;

use App\Models\User;
use App\Models\UserRole;
use Illuminate\Support\Facades\DB;

class UpdateUserRoleAction
{
    /**
     * Update primary role, toggle status, and sync user_roles.
     */
    public function execute(User $user, string $role, ?bool $status = null, array $additionalRoles = []): User
    {
        return DB::transaction(function () use ($user, $role, $status, $additionalRoles) {
            $payload = ['role' => $role];
            if (!is_null($status)) {
                $payload['status'] = $status;
            }

            $user->update($payload);

            // Sync user_roles
            $allRoles = array_unique(array_merge([$role], $additionalRoles));
            UserRole::where('user_id', $user->id)->delete();
            foreach ($allRoles as $r) {
                UserRole::create([
                    'user_id' => $user->id,
                    'role_name' => $r,
                ]);
            }

            return $user->fresh(['roles', 'profile']);
        });
    }
}
