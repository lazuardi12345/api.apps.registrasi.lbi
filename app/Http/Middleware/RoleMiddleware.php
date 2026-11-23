<?php

namespace App\Http\Middleware;

use App\Traits\ApiResponse;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class RoleMiddleware
{
    use ApiResponse;

    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     * @param  string  ...$roles
     */
    public function handle(Request $request, Closure $next, string ...$roles): Response
    {
        $user = $request->user();

        if (!$user) {
            return $this->sendError('Unauthenticated. Please log in first.', 401);
        }

        if (!empty($roles) && !$user->hasRole(...$roles)) {
            return $this->sendError('Access forbidden. You do not have permission for this role.', 403, [
                'required_roles' => $roles,
                'user_role' => $user->role,
            ]);
        }

        return $next($request);
    }
}
