<?php

namespace App\Tenancy;

use Stancl\Tenancy\Contracts\TenantWithDatabase;
use Stancl\Tenancy\TenantDatabaseManagers\PostgreSQLDatabaseManager;
use App\Models\Tenant;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class PGSQLDatabaseManager extends PostgreSQLDatabaseManager
{
    /**
     * Get the actual database name for the tenant
     */
    protected function getDatabaseName(TenantWithDatabase $tenant): string
    {
        // If it's our custom tenant with external database
        if ($tenant instanceof Tenant && $tenant->db_connection_type === 'external') {
            return $tenant->db_database;
        }

        // Use consistent manual naming for local databases
        return 'tenant_' . $tenant->getTenantKey();
    }

    public function createDatabase(TenantWithDatabase $tenant): bool
    {
        // Check if this tenant uses an external database
        if ($this->isExternalDatabase($tenant)) {
            // For external databases, assume it already exists
            return true;
        }

        // For local databases, create them normally
        $databaseName = $this->getDatabaseName($tenant);

        // Get the PDO connection directly
        $pdo = $this->database()->getPdo();

        try {
            // Execute CREATE DATABASE directly through PDO (bypasses transactions)
            $sql = "CREATE DATABASE \"{$databaseName}\" WITH ENCODING = 'UTF8' TEMPLATE=template0";
            $pdo->exec($sql);
            return true;
        } catch (\Exception $e) {
            Log::error("Failed to create database: " . $e->getMessage());
            throw $e;
        }
    }

    public function deleteDatabase(TenantWithDatabase $tenant): bool
    {
        // Only delete if it's a local database
        if ($this->isExternalDatabase($tenant)) {
            // Don't delete external databases
            return true;
        }

        $databaseName = $this->getDatabaseName($tenant);

        // Get the PDO connection directly
        $pdo = $this->database()->getPdo();

        try {
            $sql = "DROP DATABASE IF EXISTS \"{$databaseName}\"";
            $pdo->exec($sql);
            return true;
        } catch (\Exception $e) {
            Log::error("Failed to delete database: " . $e->getMessage());
            throw $e;
        }
    }

    public function makeConnectionConfig(array $baseConfig, string $databaseName): array
    {
        // Get the current tenant if we're in a tenant context
        $tenant = tenant();

        // If we have a tenant and it uses an external database
        if ($tenant instanceof Tenant && $tenant->db_connection_type === 'external') {
            return [
                'driver' => 'pgsql',
                'host' => $tenant->db_host,
                'port' => $tenant->db_port ?? 5432,
                'database' => $tenant->db_database,
                'username' => $tenant->db_username,
                'password' => $tenant->db_password,
                'charset' => 'utf8',
                'prefix' => '',
                'prefix_indexes' => true,
                'search_path' => 'public',
                'sslmode' => 'require',
            ];
        }

        // For local databases, use the default behavior
        return parent::makeConnectionConfig($baseConfig, $databaseName);
    }

    /**
     * Check if a tenant uses an external database
     */
    protected function isExternalDatabase(TenantWithDatabase $tenant): bool
    {
        return $tenant instanceof Tenant && $tenant->db_connection_type === 'external';
    }
}
