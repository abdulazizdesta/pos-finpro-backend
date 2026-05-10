<?php

namespace App\Http\Requests;

use App\Enums\UserRole;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateUserRequest extends FormRequest
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
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'name' => ['sometimes', 'string', 'min:3', 'max:255'],
            'email' => ['sometimes', 'string', 'email', Rule::unique('users', 'email')->ignore($this->route('user')->id)],
            'password' => ['sometimes', 'string', 'min:8'],
            'role' => ['sometimes', 'in:' . implode(',', array_column(UserRole::cases(), 'value'))],
            'is_active' => ['nullable', 'boolean'],
            'outlet_id' => [
                'sometimes',
                (($this->input('role') ?? $this->route('user')->role->value) === 'cashier')
                ? 'required'
                : 'nullable',
                'integer',
                'exists:outlets,id'
            ],
        ];
    }

    public function messages(): array
    {
        return [
            'name.min' => 'Name must be at least 3 characters',
            'name.max' => 'Name must not exceed 255 characters',
            'email.email' => 'Email format is invalid',
            'email.unique' => 'Email is already taken',
            'password.min' => 'Password must be at least 8 characters',
            'role.in' => 'Role must be one of: ' . implode(', ', array_column(UserRole::cases(), 'value')),
            'business_id.integer' => 'Business ID must be a number',
            'business_id.exists' => 'Business not found',
            'outlet_id.required' => 'Outlet is required for cashier',
            'outlet_id.integer' => 'Outlet ID must be a number',
            'outlet_id.exists' => 'Outlet not found',
        ];
    }
}
