<?php

namespace App\Http\Requests\Central\Tenant;

use Illuminate\Foundation\Http\FormRequest;

class TransferOwnershipRequest extends FormRequest
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
            'new_owner_id' => ['required', 'exists:users,id']
        ];
    }

    public function messages(): array
    {
        return [
            'new_owner_id.required' => 'The new owner ID is required.',
            'new_owner_id.exists' => 'The selected user is not a valid user.'
        ];
    }
}
