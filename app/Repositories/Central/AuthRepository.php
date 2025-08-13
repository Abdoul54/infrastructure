<?php

namespace App\Repositories\Central;

use App\Models\User;
use App\Repositories\Central\Contracts\AuthRepositoryInterface;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Auth;

class AuthRepository implements AuthRepositoryInterface
{
    public function login($data)
    {
        try {
            // Attempt to authenticate the user
            if (Auth::guard('web')->attempt(['email' => $data['email'], 'password' => $data['password']])) {
                $user = Auth::user();
                $token = $user->createToken('api-token')->plainTextToken;

                return [
                    'name' => $user->name,
                    'email' => $user->email,
                    'token' => $token
                ];
            } else {
                throw new \Exception('Invalid credentials', 401);
            }
        } catch (\Exception $e) {
            return [
                'success' => false,
                'error' => 'Login failed',
                'message' => $e->getMessage()
            ];
        }
    }

    public function register($data)
    {
        try {
            $data['password'] = Hash::make($data['password']);
            $user = User::create($data);

            $token = $user->createToken('api-token')->plainTextToken;

            return [
                'user' => $user,
                'token' => $token
            ];
        } catch (\Exception $e) {
            return [
                'success' => false,
                'error' => 'Registration failed',
                'message' => $e->getMessage()
            ];
        }
    }

    public function logout($data)
    {
        try {
            Auth::user()->tokens()->delete();
            return ['message' => 'Logout successful'];
        } catch (\Exception $e) {
            return [
                'success' => false,
                'error' => 'Logout failed',
                'message' => $e->getMessage()
            ];
        }
    }
}
