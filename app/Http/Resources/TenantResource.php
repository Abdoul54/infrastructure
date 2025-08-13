<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class TenantResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'owner' => $this->owner,
            'domain' => $this->getDomains(),
            'db_connection_type' => $this->db_connection_type,
            'db_credentials' => $this->when($this->db_connection_type === 'external', [
                'db_host' => $this->db_host,
                'db_port' => $this->db_port,
                'db_database' => $this->db_database,
                'db_username' => $this->db_username,
                'db_password' => $this->db_password,
            ]),
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
