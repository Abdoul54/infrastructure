<?php

namespace App\Models;

use Stancl\Tenancy\Database\Models\Tenant as BaseTenant;
use Stancl\Tenancy\Contracts\TenantWithDatabase;
use Stancl\Tenancy\Database\Concerns\HasDatabase;
use Stancl\Tenancy\Database\Concerns\HasDomains;

class Tenant extends BaseTenant implements TenantWithDatabase
{
    use HasDatabase;
    use HasDomains;

    protected $fillable = [
        'id',
        'data',
        'db_connection_type',
        'db_host',
        'db_port',
        'db_database',
        'db_username',
        'db_password',
        'owner_id',
    ];

    // Hide sensitive fields from JSON output
    protected $hidden = [
        'db_password',
    ];

    // Cast db_password to encrypted
    protected $casts = [
        'data' => 'array',
        'db_password' => 'encrypted',
    ];

    public static function getCustomColumns(): array
    {
        return [
            'id',
            'db_connection_type',
            'db_host',
            'db_port',
            'db_database',
            'db_username',
            'db_password',
            'owner_id',
        ];
    }

    public function getData(): array
    {
        $data = json_decode($this->getRawOriginal('data') ?? '{}', true) ?? [];
        return $data;
    }

    public function getDomains(): array
    {
        return $this->domains->pluck('domain')->toArray();
    }


    /**
     * Define the owner relationship
     */
    public function owner()
    {
        return $this->belongsTo(User::class, 'owner_id');
    }

    /**
     * Override the __get magic method to intercept 'database' property access
     */
    public function __get($key)
    {
        // If trying to access 'database' as a property during pagination,
        // return null to prevent relationship loading
        if ($key === 'database') {
            return null;
        }

        return parent::__get($key);
    }

    /**
     * Override to prevent Laravel from treating 'database' as a relationship
     */
    public function getRelationValue($key)
    {
        if ($key === 'database') {
            return null;
        }

        return parent::getRelationValue($key);
    }

    /**
     * Override hasGetMutator to prevent 'database' from being treated as an accessor
     */
    public function hasGetMutator($key)
    {
        if ($key === 'database') {
            return false;
        }

        return parent::hasGetMutator($key);
    }

    /**
     * Override isRelation to explicitly exclude 'database'
     */
    public function isRelation($key)
    {
        if ($key === 'database') {
            return false;
        }

        // Check if parent method exists before calling
        if (method_exists(parent::class, 'isRelation')) {
            return parent::isRelation($key);
        }

        return false;
    }
}
