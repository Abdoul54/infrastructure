<?php

namespace App\Repositories\Tenant\Contracts;

interface AuthRepositoryInterface
{
    public function login($data);
    public function register($data);
    public function logout($data);
}
