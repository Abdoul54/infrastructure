<?php

namespace App\Repositories\Central\Contracts;

interface AuthRepositoryInterface
{
    public function login($data);
    public function register($data);
    public function logout($data);
}
