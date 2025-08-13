<?php

namespace App\Repositories\Central\Contracts;

interface TenantRepositoryInterface
{
    public function listTenants(array $params);
    public function findTenantById(string $id);
    public function createTenant(array $data);
    public function deleteTenant(string $id);
    public function transferOwnership(string $tenantId, int $newOwnerId);
}
