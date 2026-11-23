<?php

namespace App\Http\Controllers\Api;

use App\Actions\Auth\LoginUserAction;
use App\Actions\Auth\RegisterUserAction;
use App\Http\Requests\Auth\LoginRequest;
use App\Http\Requests\Auth\RegisterRequest;
use App\Http\Resources\UserResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Tymon\JWTAuth\Facades\JWTAuth;

class AuthApiController extends BaseApiController
{
    /**
     * Register a new user account.
     */
    public function register(RegisterRequest $request, RegisterUserAction $action): JsonResponse
    {
        $result = $action->execute($request->validated());

        return $this->sendSuccess([
            'user' => new UserResource($result['user']),
            'token' => $result['token'],
            'token_type' => $result['token_type'],
        ], 'Registration successful', 201);
    }

    /**
     * Authenticate and return an API bearer token.
     */
    public function login(LoginRequest $request, LoginUserAction $action): JsonResponse
    {
        $result = $action->execute($request->email, $request->password);

        return $this->sendSuccess([
            'user' => new UserResource($result['user']),
            'token' => $result['token'],
            'token_type' => $result['token_type'],
        ], 'Login successful');
    }

    /**
     * Get the authenticated user profile.
     */
    public function me(Request $request): JsonResponse
    {
        return $this->sendSuccess(
            new UserResource($request->user()->load('profile', 'roles')),
            'User profile retrieved successfully'
        );
    }

    /**
     * Revoke current access token.
     */
    public function logout(Request $request): JsonResponse
    {
        JWTAuth::invalidate(JWTAuth::getToken());

        return $this->sendSuccess(null, 'Successfully logged out');
    }
}
