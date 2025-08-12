<?php

namespace App\Http\Controllers\Central;

use App\Http\Controllers\Controller;
use App\Models\Tenant;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

/**
 * @group Tenants
 * 
 * This group handles all tenant-related endpoints.
 *
 * @authenticated
 */
class TenantController extends Controller
{
    /**
     * Get all tenants.
     * This endpoint retrieves a list of all tenants.
     *
     * @authenticated
     */
    public function all()
    {
        $tenants = Tenant::with('domains')->get();

        return response()->json([
            'tenants' => $tenants->map(function ($tenant) {
                $data = json_decode($tenant->getRawOriginal('data') ?? '{}', true) ?? [];
                return [
                    'id' => $tenant->id,
                    'data' => $data,
                    'domains' => $tenant->domains->pluck('domain'),
                    'db_type' => $tenant->db_connection_type ?? 'local',
                    'created_at' => $tenant->created_at,
                ];
            })
        ]);
    }

    /**
     * Create a new tenant.
     * This endpoint allows users to create a new tenant.
     *
     * @bodyParam id string required The unique identifier for the tenant.
     * @bodyParam domain string required The domain name for the tenant.
     * @bodyParam db_connection_type string required The database connection type (local or external).
     * @bodyParam db_host string required_if:db_connection_type,external The database host.
     * @bodyParam db_port numeric required_if:db_connection_type,external The database port.
     * @bodyParam db_database string required_if:db_connection_type,external The database name.
     * @bodyParam db_username string required_if:db_connection_type,external The database username.
     * @bodyParam db_password string required_if:db_connection_type,external The database password.
     *
     * @authenticated
     */
    public function create(Request $request)
    {
        $validator = Validator::make($request->all(), [
            // Tenant basics
            'id' => 'required|string|alpha_dash|unique:tenants,id',
            'domain' => 'required|string|unique:domains,domain',

            // Database configuration
            'db_connection_type' => ['required', Rule::in(['local', 'external'])],

            // External database fields (required if type is external)
            'db_host' => 'required_if:db_connection_type,external|nullable|string',
            'db_port' => 'required_if:db_connection_type,external|nullable|numeric',
            'db_database' => 'required_if:db_connection_type,external|nullable|string',
            'db_username' => 'required_if:db_connection_type,external|nullable|string',
            'db_password' => 'required_if:db_connection_type,external|nullable|string',

        ]);

        if ($validator->fails()) {
            return response()->json([
                'error' => 'Validation failed',
                'messages' => $validator->errors()
            ], 422);
        }

        $validated = $validator->validated();

        // Test external database connection if specified
        if ($validated['db_connection_type'] === 'external') {
            if (!$this->testDatabaseConnection($validated)) {
                return response()->json([
                    'error' => 'Cannot connect to external database',
                    'message' => 'Please verify the database credentials and ensure the database exists'
                ], 422);
            }
        }

        try {
            // Prepare tenant data
            $tenantData = [
                'id' => $validated['id'],
                'owner_id' => Auth::id(),
                'db_connection_type' => $validated['db_connection_type'],
            ];

            // Add external database configuration if applicable
            if ($validated['db_connection_type'] === 'external') {
                $tenantData['db_host'] = $validated['db_host'];
                $tenantData['db_port'] = $validated['db_port'] ?? 5432;
                $tenantData['db_database'] = $validated['db_database'];
                $tenantData['db_username'] = $validated['db_username'];
                $tenantData['db_password'] = $validated['db_password']; // Laravel handles encryption via cast
            }

            // Create tenant WITHOUT transaction for local databases
            // because CREATE DATABASE cannot run inside a transaction
            $tenant = Tenant::create($tenantData);

            // Now use transaction for domain creation
            DB::beginTransaction();
            try {
                // Create domain
                $tenant->domains()->create([
                    'domain' => $validated['domain']
                ]);

                DB::commit();
            } catch (\Exception $e) {
                DB::rollback();

                // Clean up: Delete the tenant if domain creation failed
                $tenant->delete();

                throw $e;
            }

            // Run migrations for the tenant
            $this->setupTenantDatabase($tenant);

            return response()->json([
                'message' => 'Tenant created successfully',
                'tenant' => [
                    'id' => $tenant->id,
                    'domain' => $validated['domain'],
                    'db_type' => $tenant->db_connection_type,
                    'data' => json_decode($tenant->getRawOriginal('data') ?? '{}', true) ?? [],
                ]
            ], 201);
        } catch (\Exception $e) {
            return response()->json([
                'error' => 'Failed to create tenant',
                'message' => config('app.debug') ? $e->getMessage() : 'An error occurred during tenant creation'
            ], 500);
        }
    }

    /**
     * Get tenant details.
     * This endpoint retrieves details of a specific tenant by ID.
     *
     * @urlParam id string required The unique identifier for the tenant.
     *
     * @authenticated
     */
    public function show($id)
    {
        $tenant = Tenant::with('domains')->find($id);

        if (!$tenant) {
            return response()->json([
                'error' => 'Tenant not found'
            ], 404);
        }

        return response()->json([
            'tenant' => [
                'id' => $tenant->id,
                'domains' => $tenant->domains->pluck('domain'),
                'db_type' => $tenant->db_connection_type,
                'data' => $tenant->data,
                'created_at' => $tenant->created_at,
            ]
        ]);
    }

    /**
     * Delete a tenant.
     * This endpoint deletes a specific tenant by ID.
     *
     * @urlParam id string required The unique identifier for the tenant.
     *
     * @authenticated
     */
    public function delete($id)
    {
        $tenant = Tenant::find($id);

        if (!$tenant) {
            return response()->json([
                'error' => 'Tenant not found'
            ], 404);
        }

        try {
            // This will trigger the database deletion (local only) via TenancyServiceProvider
            $tenant->delete();

            return response()->json([
                'message' => 'Tenant deleted successfully'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'error' => 'Failed to delete tenant',
                'message' => config('app.debug') ? $e->getMessage() : 'An error occurred'
            ], 500);
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
    }
}
