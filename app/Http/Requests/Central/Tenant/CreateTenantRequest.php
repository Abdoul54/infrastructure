<?php

namespace App\Http\Requests\Central\Tenant;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class CreateTenantRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            // 'id' => 'required|string|alpha_dash|unique:tenants,id',
            'name' => 'required|string|unique:tenants,name',
            'domain' => 'required|string|unique:domains,domain',

            // Database configuration
            'db_connection_type' => ['required', Rule::in(['local', 'external'])],

            // External database fields (required if type is external)
            'db_host' => 'required_if:db_connection_type,external|nullable|string',
            'db_port' => 'required_if:db_connection_type,external|nullable|numeric',
            'db_database' => 'required_if:db_connection_type,external|nullable|string',
            'db_username' => 'required_if:db_connection_type,external|nullable|string',
            'db_password' => 'required_if:db_connection_type,external|nullable|string',
        ];
    }


    public function messages(): array
    {
        return [
            // 'id.required' => 'The tenant ID is required.',
            'name.required' => 'The tenant name is required.',
            'domain.required' => 'The tenant domain is required.',
            'db_connection_type.required' => 'The database connection type is required.',
            'db_host.required_if' => 'The database host is required when using an external connection.',
            'db_port.required_if' => 'The database port is required when using an external connection.',
            'db_database.required_if' => 'The database name is required when using an external connection.',
            'db_username.required_if' => 'The database username is required when using an external connection.',
            'db_password.required_if' => 'The database password is required when using an external connection.',
        ];
    }
}
