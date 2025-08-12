<?php

namespace App\Repositories\Central\Interfaces;

interface AuthRepositoryInterface
{
    public function login($data);
    public function register($data);
    public function logout($data);
}
