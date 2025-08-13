<?php

namespace App\Http\Controllers\Tenant;

use App\Http\Controllers\Controller;
use App\Http\Requests\Tenant\Auth\LoginRequest;
use App\Http\Requests\Tenant\Auth\RegisterRequest;
use App\Services\Tenant\AuthService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Knuckles\Scribe\Attributes\Group;

/**
 * @group Authentication
 * 
 * This group handles all authentication-related endpoints.
 */
class AuthController extends Controller
{
    protected $authService;

    public function __construct(AuthService $authService)
    {
        $this->authService = $authService;
    }

    /**
     * Register a new user.
     * This endpoint allows users to create a new account.
     */
    public function register(RegisterRequest $request): JsonResponse
    {
        try {
            $data = $request->validated();
            $result = $this->authService->register($data);

            if (isset($result['error'])) {
                return response()->json($result, 422);
            }

            return response()->json([
                'success' => true,
                'message' => 'Registration successful',
                'user' => $result['user'],
                'token' => $result['token']
            ], 201);
        } catch (\Throwable $th) {
            return response()->json([
                'success' => false,
                'error' => 'Registration failed',
                'message' => $th->getMessage()
            ], $th->getCode() ?: 422);
        }
    }

    /**
     * Log in a user.
     * This endpoint allows users to log in and receive an authentication token.
     */
    public function login(LoginRequest $request): JsonResponse
    {
        try {
            $data = $request->validated();
            $result = $this->authService->login($data);

            if (isset($result['error'])) {
                return response()->json($result, 401);
            }

            return response()->json([
                'success' => true,
                'message' => 'Login successful',
                'user' => $result,
            ]);
        } catch (\Throwable $th) {
            return response()->json([
                'success' => false,
                'error' => 'Login failed',
                'message' => $th->getMessage()
            ], 401);
        }
    }

    /**
     * Log out a user.
     * This endpoint allows users to log out and invalidate their authentication token.
     * @authenticated
     */
    public function logout(Request $request): JsonResponse
    {
        try {
            $result = $this->authService->logout([]);

            if (isset($result['error'])) {
                return response()->json($result, 401);
            }

            return response()->json([
                'success' => true,
                'message' => 'Logout successful'
            ]);
        } catch (\Throwable $th) {
            return response()->json([
                'success' => true,
                'error' => 'Logout failed',
                'message' => $th->getMessage()
            ], 500);
        }
    }
}
