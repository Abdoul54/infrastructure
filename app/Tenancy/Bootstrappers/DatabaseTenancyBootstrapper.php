<?php

namespace App\Tenancy\Bootstrappers;

use Stancl\Tenancy\Contracts\TenancyBootstrapper;
use Stancl\Tenancy\Contracts\Tenant;
use App\Models\Tenant as TenantModel;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Config;;

class DatabaseTenancyBootstrapper implements TenancyBootstrapper
{
    protected ?string $originalDefaultConnection = null;

    public function bootstrap(Tenant $tenant)
    {
        // Store the original connection
        $this->originalDefaultConnection = DB::getDefaultConnection();

        // The tenant connection name is typically 'tenant'
        $connectionName = 'tenant';

        // If it's an external database tenant, set up the connection differently
        if ($tenant instanceof TenantModel && $tenant->db_connection_type === 'external') {
            $this->configureExternalDatabase($tenant, $connectionName);
        } else {
            $this->configureLocalDatabase($tenant, $connectionName);
        }

        // Switch to the tenant connection
        DB::setDefaultConnection($connectionName);
    }

    public function revert()
    {
        // Switch back to the original connection
        if ($this->originalDefaultConnection !== null) {
            DB::setDefaultConnection($this->originalDefaultConnection);
        }

        // Purge the tenant connection
        DB::purge('tenant');
    }

    protected function configureExternalDatabase(TenantModel $tenant, string $connectionName)
    {
        // Configure the external database connection with SSL support
        Config::set("database.connections.{$connectionName}", [
            'driver' => 'pgsql',
            'host' => $tenant->db_host,
            'port' => $tenant->db_port ?? 5432,
            'database' => $tenant->db_database,
            'username' => $tenant->db_username,
            'password' => $tenant->db_password, // Laravel automatically decrypts this
            'charset' => 'utf8',
            'prefix' => '',
            'prefix_indexes' => true,
            'search_path' => 'public',
            'sslmode' => 'require', // Changed for Neon and other cloud providers
            'options' => [
                \PDO::ATTR_PERSISTENT => false,
            ],
        ]);

        // Purge and reconnect
        DB::purge($connectionName);
        DB::reconnect($connectionName);
    }

    protected function configureLocalDatabase(Tenant $tenant, string $connectionName)
    {
        // Get the base configuration (usually from 'pgsql' connection)
        $baseConfig = Config::get('database.connections.' . Config::get('tenancy.database.central_connection', 'pgsql'));

        // Get the database name - using the tenant key (id)
        $databaseName = 'tenant_' . $tenant->getTenantKey();

        Config::set("database.connections.{$connectionName}", array_merge($baseConfig, [
            'database' => $databaseName,
        ]));

        // Purge and reconnect
        DB::purge($connectionName);
        DB::reconnect($connectionName);
    }
}
