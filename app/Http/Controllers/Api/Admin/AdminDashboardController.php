<?php

namespace App\Http\Controllers\Api\Admin;

use App\Actions\Admin\Dashboard\GetAdminAnalyticsAction;
use App\Actions\Admin\Dashboard\GetEnrollmentAnalyticsAction;
use App\Http\Controllers\Api\BaseApiController;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AdminDashboardController extends BaseApiController
{
    /**
     * Determine the allowed divisions for the current authenticated user.
     * Non-superadmins are strictly locked to their program.
     */
    private function resolveAllowedDivisions(Request $request): ?array
    {
        $user = $request->user();
        if (! $user) {
            return null;
        }

        // 1. Super Administrator & Finance can view any or all divisions
        if ($user->role === 'administrator' || $user->role === 'finance') {
            $queryDiv = $request->query('division');
            if ($queryDiv && $queryDiv !== 'all') {
                if ($queryDiv === 'ppb') {
                    return ['ppb', 'ppb_english', 'ppb_foreign', 'testing'];
                }
                if ($queryDiv === 'ppb_english') {
                    return ['ppb', 'ppb_english', 'testing'];
                }
                return [$queryDiv];
            }
            return null;
        }

        // 2. Admin PPB: locked to PPB English & Foreign
        if ($user->role === 'admin_ppb' || ($user->relationLoaded('roles') && $user->roles()->where('role_name', 'admin_ppb')->exists())) {
            $queryDiv = $request->query('division');
            if ($queryDiv === 'ppb_foreign') {
                return ['ppb_foreign'];
            }
            if ($queryDiv === 'ppb_english') {
                return ['ppb', 'ppb_english'];
            }
            return ['ppb', 'ppb_english', 'ppb_foreign', 'testing'];
        }

        // 3. Admin BIPA: strictly locked to BIPA
        if ($user->role === 'admin_bipa' || ($user->relationLoaded('roles') && $user->roles()->where('role_name', 'admin_bipa')->exists())) {
            return ['bipa'];
        }

        // 4. Admin PPP: strictly locked to PPP (Translation)
        if ($user->role === 'admin_ppp' || ($user->relationLoaded('roles') && $user->roles()->where('role_name', 'admin_ppp')->exists())) {
            return ['ppp'];
        }

        // Default: if division query requested, return it, otherwise null
        $queryDiv = $request->query('division');
        return $queryDiv && $queryDiv !== 'all' ? [$queryDiv] : null;
    }

    public function index(Request $request, GetAdminAnalyticsAction $action): JsonResponse
    {
        $allowedDivisions = $this->resolveAllowedDivisions($request);
        $data = $action->execute($allowedDivisions);

        // Include current program scope info in response
        $user = $request->user();
        $data['program_scope'] = [
            'role' => $user?->role,
            'allowed_divisions' => $allowedDivisions,
            'is_scoped' => $allowedDivisions !== null,
        ];

        return $this->sendSuccess($data, 'Admin dashboard metrics retrieved successfully');
    }

    public function enrollmentAnalytics(Request $request, GetEnrollmentAnalyticsAction $action): JsonResponse
    {
        $allowedDivisions = $this->resolveAllowedDivisions($request);
        $data = $action->execute($allowedDivisions);

        return $this->sendSuccess($data, 'Enrollment analytics retrieved successfully');
    }
}
