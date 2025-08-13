<?php

namespace App\Services\Central;

use App\Repositories\Central\Contracts\TenantRepositoryInterface;

class TenantService
{
    protected $tenantRepository;

    /**
     * Create a new service instance.
     */
    public function __construct(TenantRepositoryInterface $tenantRepository)
    {
        $this->tenantRepository = $tenantRepository;
    }

    public function listTenants()
    {
        return $this->tenantRepository->listTenants();
    }

    public function findTenantById(string $id)
    {
        return $this->tenantRepository->findTenantById($id);
    }

    public function createTenant(array $data)
    {
        return $this->tenantRepository->createTenant($data);
    }


    public function deleteTenant(string $id)
    {
        return $this->tenantRepository->deleteTenant($id);
    }
}
