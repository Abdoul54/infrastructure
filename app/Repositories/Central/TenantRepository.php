<?php

namespace App\Repositories\Central;

use App\Models\Tenant;
use App\Models\User;
use App\Repositories\Central\Contracts\TenantRepositoryInterface;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

class TenantRepository implements TenantRepositoryInterface
{

    public function listTenants(array $params)
    {
        try {
            // Sanitize and validate parameters
            $page = max(1, intval($params['page'] ?? 1));
            $perPage = max(1, min(100, intval($params['per_page'] ?? 10)));
            $search = trim($params['search'] ?? '');

            // Validate sorting
            $sortBy = $this->validateSortField($params['sort_by'] ?? 'created_at');
            $sortOrder = $this->validateSortOrder($params['sort_order'] ?? 'desc');

            // Build query
            $query = DB::table('tenants');

            // Apply search filters
            if ($search) {
                $this->applySearchFilters($query, $search);
            }

            // Apply sorting
            $this->applySorting($query, $sortBy, $sortOrder);

            // Get total count
            $total = $query->count();

            // Get paginated results
            $items = $query
                ->limit($perPage)
                ->offset(($page - 1) * $perPage)
                ->get();

            // Convert to Tenant models
            $tenants = $this->hydrateModels($items);

            // Create and return paginator
            return new LengthAwarePaginator(
                $tenants,
                $total,
                $perPage,
                $page,
                [
                    'path' => request()->url(),
                    'query' => request()->query(),
                ]
            );
        } catch (\Throwable $th) {
            Log::error('Failed to retrieve tenants', [
                'error' => $th->getMessage(),
                'params' => $params
            ]);

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
            $tenant->name = $data['name'];
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
                'name' => $tenant->name,
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

    public function transferOwnership(string $tenantId, int $newOwnerId)
    {
        try {
            $tenant = Tenant::findOrFail($tenantId);
            $newOwner = User::findOrFail($newOwnerId);
            $userId = Auth::id();

            if ($tenant->owner_id !== $userId) {
                throw new \Exception('You do not have permission to transfer ownership of this tenant');
            }

            $tenant->owner_id = $newOwner->id;
            $tenant->save();

            return [
                'success' => true,
                'message' => 'Tenant ownership transferred successfully',
                'tenant' => $tenant
            ];
        } catch (\Throwable $th) {
            Log::error('Failed to transfer tenant ownership', [
                'tenant_id' => $tenantId,
                'new_owner_id' => $newOwnerId,
                'error' => $th->getMessage()
            ]);
            return [
                'success' => false,
                'error' => 'Failed to transfer tenant ownership',
                'message' => $th->getMessage()
            ];
        }
    }


    /**
     * Validate sort field
     *
     * @param string $field
     * @return string
     */
    private function validateSortField(string $field): string
    {
        $allowedFields = [
            'id',
            'name',
            'created_at',
            'updated_at',
            'db_connection_type',
            'owner_id'
        ];

        return in_array($field, $allowedFields) ? $field : 'created_at';
    }

    /**
     * Validate sort order
     *
     * @param string $order
     * @return string
     */
    private function validateSortOrder(string $order): string
    {
        $order = strtolower($order);
        return in_array($order, ['asc', 'desc']) ? $order : 'desc';
    }

    /**
     * Apply search filters to query
     *
     * @param \Illuminate\Database\Query\Builder $query
     * @param string $search
     * @return void
     */
    private function applySearchFilters($query, string $search): void
    {
        $searchPattern = '%' . str_replace(['%', '_'], ['\%', '\_'], $search) . '%';

        $query->where(function ($q) use ($searchPattern) {
            // Search in JSON data->name field
            $q->whereRaw("LOWER(data->>'name') LIKE LOWER(?)", [$searchPattern])
                ->orWhereRaw("LOWER(id) LIKE LOWER(?)", [$searchPattern]);

            // Search in domains if table exists
            if (Schema::hasTable('domains')) {
                $q->orWhereExists(function ($subQuery) use ($searchPattern) {
                    $subQuery->select(DB::raw(1))
                        ->from('domains')
                        ->whereRaw('domains.tenant_id = tenants.id')
                        ->whereRaw("LOWER(domains.domain) LIKE LOWER(?)", [$searchPattern]);
                });
            }
        });
    }

    /**
     * Apply sorting to query
     *
     * @param \Illuminate\Database\Query\Builder $query
     * @param string $sortBy
     * @param string $sortOrder
     * @return void
     */
    private function applySorting($query, string $sortBy, string $sortOrder): void
    {
        if ($sortBy === 'name') {
            // Sort by JSON field in PostgreSQL
            $query->orderByRaw("data->>'name' {$sortOrder} NULLS LAST");
        } else {
            $query->orderBy($sortBy, $sortOrder);
        }
    }

    /**
     * Hydrate database results into Tenant models
     *
     * @param \Illuminate\Support\Collection $items
     * @return \Illuminate\Support\Collection
     */
    private function hydrateModels($items)
    {
        return $items->map(function ($item) {
            $tenant = new Tenant();
            $tenant->forceFill((array) $item);
            $tenant->exists = true;
            $tenant->syncOriginal();

            // Safely load relationships
            try {
                $tenant->load(['owner', 'domains']);
            } catch (\Exception $e) {
                Log::warning('Could not load tenant relationships', [
                    'tenant_id' => $tenant->id,
                    'error' => $e->getMessage()
                ]);
            }

            return $tenant;
        });
    }
}
