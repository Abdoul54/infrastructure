<?php

namespace App\Repositories\Central\Contracts;

interface TenantRepositoryInterface
{
    public function listTenants();
    public function findTenantById(string $id);
    public function createTenant(array $data);
    public function deleteTenant(string $id);
}
