<?php

namespace App\Repositories\Central;

use App\Models\Tenant;
use App\Repositories\Central\Contracts\TenantRepositoryInterface;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class TenantRepository implements TenantRepositoryInterface
{
    public function listTenants()
    {
        try {
            return Tenant::paginate(10);
        } catch (\Throwable $th) {
            return [
                'success' => false,
                'error' => 'Failed to retrieve tenants',
                'message' => $th->getMessage()
            ];
        }
    }

    public function findTenantById(string $id)
    {
        try {
            $tenant = Tenant::findOrFail($id);

            if (!$tenant) {
                throw new \Exception('Tenant not found');
            }

            return $tenant;
        } catch (\Throwable $th) {
            return [
                'success' => false,
                'error' => 'Failed to retrieve tenant',
                'message' => $th->getMessage()
            ];
        }
    }

    public function createTenant(array $data)
    {
        if ($data['db_connection_type'] === 'external') {
            if (!$this->testDatabaseConnection($data)) {
                return [
                    'success' => false,
                    'error' => 'Cannot connect to external database',
                    'message' => 'Please verify the database credentials and ensure the database exists'
                ];
            }
        }

        try {
            $tenant = new Tenant();
            $tenant->id = (string) Str::uuid();
            $tenant->owner_id = Auth::id();
            $tenant->db_connection_type = $data['db_connection_type'];

            // Set database credentials if using external databse
            if ($data['db_connection_type'] === 'external') {
                $tenant->db_host = $data['db_host'];
                $tenant->db_port = $data['db_port'];
                $tenant->db_database = $data['db_database'];
                $tenant->db_username = $data['db_username'];
                $tenant->db_password = $data['db_password'];
            }

            $tenant->save();

            DB::beginTransaction();
            try {
                $tenant->domains()->create([
                    'domain' => $data['domain']
                ]);

                DB::commit();
                Log::info('Domain created successfully', ['domain' => $data['domain']]);
            } catch (\Exception $e) {
                DB::rollback();

                $tenant->delete();
                Log::error('Domain creation failed, tenant rolled back', ['error' => $e->getMessage()]);

                throw $e;
            }

            // Run migrations for the tenant
            $this->setupTenantDatabase($tenant);

            return [
                'id' => $tenant->id,
                'domain' => $data['domain'],
                'db_connection_type' => $tenant->db_connection_type,
                'db_credentials' => $tenant->db_connection_type === 'external' ? [
                    'db_host' => $tenant->db_host,
                    'db_port' => $tenant->db_port,
                    'db_database' => $tenant->db_database,
                    'db_username' => $tenant->db_username,
                    'db_password' => $tenant->db_password,
                ] : null,
                'data' => $tenant->getData() ?? [],
            ];
        } catch (\Exception $e) {
            Log::error('Tenant creation failed', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return [
                'success' => false,
                'error' => 'Failed to create tenant',
                'message' => $e->getMessage()
            ];
        }
    }

    public function deleteTenant(string $id)
    {
        try {
            $tenant = Tenant::findOrFail($id);

            if (!$tenant) {
                throw new \Exception('Tenant not found');
            }

            $tenant->delete();
            return true;
        } catch (\Exception $e) {
            Log::error('Failed to delete tenant', [
                'tenant_id' => $id,
                'error' => $e->getMessage()
            ]);
            return [
                'success' => false,
                'error' => 'Failed to delete tenant',
                'message' => $e->getMessage()
            ];
        }
    }

    protected function testDatabaseConnection(array $config): bool
    {
        try {
            $dsn = sprintf(
                "pgsql:host=%s;port=%s;dbname=%s;sslmode=require",
                $config['db_host'],
                $config['db_port'] ?? 5432,
                $config['db_database']
            );

            $pdo = new \PDO(
                $dsn,
                $config['db_username'],
                $config['db_password'],
                [
                    \PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION,
                    \PDO::ATTR_TIMEOUT => 5,
                ]
            );

            // Test with a simple query
            $pdo->query('SELECT 1');

            return true;
        } catch (\Exception $e) {
            Log::error('External database connection test failed: ' . $e->getMessage());
            return false;
        }
    }

    protected function setupTenantDatabase(Tenant $tenant): void
    {
        try {
            tenancy()->initialize($tenant);

            // Run tenant migrations
            Artisan::call('migrate', [
                '--path' => 'database/migrations/tenant',
                '--force' => true,
            ]);

            // Optionally run seeders
            if (config('tenancy.seeding.tenant_seed_class')) {
                Artisan::call('db:seed', [
                    '--class' => config('tenancy.seeding.tenant_seed_class'),
                    '--force' => true,
                ]);
            }

            tenancy()->end();

            Log::info('Tenant database setup completed', ['tenant_id' => $tenant->id]);
        } catch (\Exception $e) {
            Log::error('Failed to setup tenant database', [
                'tenant_id' => $tenant->id,
                'error' => $e->getMessage()
            ]);
            throw $e;
        }
    }
}
