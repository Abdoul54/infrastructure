<?php

namespace App\Repositories\Central;

use App\Models\User;
use App\Repositories\Central\Interfaces\AuthRepositoryInterface;

class AuthRepository implements AuthRepositoryInterface
{
    public function login($data)
    {
        try {
            $data['password'] = bcrypt($data['password']);
            $user = User::create($data);

            $token = $user->createToken('api-token')->plainTextToken;

            return [
                'user' => $user,
                'token' => $token
            ];
        } catch (\Exception $e) {
            return [
                'error' => 'Login failed',
                'message' => $e->getMessage()
            ];
        }
    }

    public function register($data)
    {
        // Implement register logic
    }

    public function logout($data)
    {
        // Implement logout logic
    }
}
