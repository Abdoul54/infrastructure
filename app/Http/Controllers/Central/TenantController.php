<?php

namespace App\Http\Controllers\Central;

use App\Http\Controllers\Controller;
use App\Http\Requests\Central\Tenant\CreateTenantRequest;
use App\Http\Requests\Central\Tenant\TransferOwnershipRequest;
use App\Http\Resources\TenantCollection;
use App\Http\Resources\TenantResource;
use App\Services\Central\TenantService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

/**
 * @group Tenants
 * 
 * This group handles all tenant-related endpoints.
 *
 * @authenticated
 */
class TenantController extends Controller
{
    protected $tenantService;

    public function __construct(TenantService $tenantService)
    {
        $this->tenantService = $tenantService;
    }

    /**
     * Get all tenants.
     * This endpoint retrieves a list of all tenants.
     *
     * @queryParam page integer The page number to retrieve. Example: 1
     * @queryParam per_page integer The number of items per page. Example: 10
     * @queryParam sort_by  string The field to sort by. Enum: created_at,id,name,updated_at,db_connection_type,owner_id.
     * @queryParam sort_order string The order to sort (asc or desc). Enum: asc,desc.
     * @queryParam search string The search term to filter tenants. Example: tenant1
     *
     * @authenticated
     */
    public function list_tenants(Request $request)
    {
        try {
            $params['per_page'] = $request->get('per_page', 15);
            $params['page'] = $request->get('page', 1);
            $params['sort_by'] = $request->get('sort_by', 'created_at');
            $params['sort_order'] = $request->get('sort_order', 'desc');
            $params['search'] = $request->get('search', null);

            $tenants = $this->tenantService->listTenants($params);

            if (isset($tenants['error'])) {
                return response()->json($tenants, 422);
            }

            return response()->json(new TenantCollection($tenants));
        } catch (\Throwable $th) {
            Log::error($th);
            return response()->json([
                'success' => false,
                'error' => 'Failed to retrieve tenants',
                'message' => $th->getMessage()
            ], 500);
        }
    }

    /**
     * Create a new tenant.
     * This endpoint allows users to create a new tenant.
     *
     * @authenticated
     */
    public function create(CreateTenantRequest $request)
    {
        try {
            $validated = $request->validated();
            $tenant = $this->tenantService->createTenant($validated);

            if (isset($tenant['error'])) {
                return response()->json($tenant, 422);
            }

            return response()->json([
                'success' => true,
                'message' => 'Tenant Created Successfully',
                'tenant' => $tenant
            ]);
        } catch (\Throwable $th) {
            Log::error($th);
            return response()->json([
                'success' => false,
                'error' => 'Failed to create tenant',
                'message' => $th->getMessage()
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
        try {
            $tenant = $this->tenantService->findTenantById($id);

            if (isset($tenant['error'])) {
                return response()->json($tenant, 404);
            }

            return response()->json([
                'success' => true,
                'message' => 'Tenant retrieved successfully',
                'data' => new TenantResource($tenant)
            ]);
        } catch (\Throwable $th) {
            Log::error($th);
            return response()->json([
                'success' => false,
                'error' => 'Failed to retrieve tenant',
                'message' => $th->getMessage()
            ], 500);
        }
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
        try {
            $tenant = $this->tenantService->deleteTenant($id);

            if (isset($tenant['error'])) {
                return response()->json($tenant, 404);
            }

            return response()->json([
                'success' => true,
                'message' => 'Tenant Deleted Successfully',
            ]);
        } catch (\Exception $e) {
            Log::error($e);
            return response()->json([
                'success' => false,
                'error' => 'Failed to delete tenant',
                'message' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Transfer ownership of a tenant.
     * This endpoint allows the current owner of a tenant to transfer ownership to another user.
     *
     * @authenticated
     */
    public function transferOwnership(TransferOwnershipRequest $request, $id)
    {
        try {
            $validated = $request->validated();
            $result = $this->tenantService->transferOwnership($id, $validated['new_owner_id']);

            if (isset($result['error'])) {
                return response()->json($result, 422);
            }

            return response()->json([
                'success' => true,
                'message' => 'Tenant ownership transferred successfully',
                'tenant' => $result['tenant']
            ]);
        } catch (\Throwable $th) {
            Log::error($th);
            return response()->json([
                'success' => false,
                'error' => 'Failed to transfer tenant ownership',
                'message' => $th->getMessage()
            ], 500);
        }
    }
}
