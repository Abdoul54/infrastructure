<?php

namespace App\Services\Tenant;

use App\Repositories\Tenant\Contracts\AuthRepositoryInterface;

class AuthService
{
    protected $authRepository;

    public function __construct(AuthRepositoryInterface $authRepository)
    {
        $this->authRepository = $authRepository;
    }

    public function login($data)
    {
        return $this->authRepository->login($data);
    }

    public function register($data)
    {
        return $this->authRepository->register($data);
    }

    public function logout($data)
    {
        return $this->authRepository->logout($data);
    }
}
