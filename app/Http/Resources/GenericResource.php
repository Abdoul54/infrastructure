<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class GenericResource extends JsonResource
{
    protected $fields;

    public function __construct($resource, array $fields = null)
    {
        parent::__construct($resource);
        $this->fields = $fields;
    }

    public function toArray($request)
    {
        $data = [];

        if ($this->fields) {
            foreach ($this->fields as $field) {
                if (isset($this->$field)) {
                    $data[$field] = $this->$field;
                }
            }
        } else {
            // fallback: return all attributes
            $data = $this->resource->toArray();
        }

        return $data;
    }
}
