<?php

namespace App\Http\Controllers\Central;

use App\Http\Controllers\Controller;
use App\Http\Requests\Central\Auth\LoginRequest;
use App\Http\Resources\GenericResource;
use App\Models\User;
use App\Services\Central\AuthService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
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
    public function register(Request $request)
    {
        $data = $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|string|email|max:255|unique:users',
            'password' => 'required|string|min:8|confirmed',
        ]);

        $data['password'] = bcrypt($data['password']);
        $user = User::create($data);

        $token = $user->createToken('api-token')->plainTextToken;

        return response()->json([
            'message' => 'Registration successful',
            'user' => $user,
            'token' => $token
        ], 201);
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
                throw new \Exception($result['error'], 401);
            }

            return response()->json([
                'message' => 'Login successful',
                'user' => new GenericResource($result, ['name', 'email']),
                'token' => $result['token']
            ]);
        } catch (\Throwable $th) {
            return response()->json([
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
    public function logout(Request $request)
    {
        $user = Auth::user();
        $user->tokens()->delete();

        return response()->json([
            'message' => 'Logout successful'
        ]);
    }
}
