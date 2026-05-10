<?php

namespace App\Http\Requests;

use App\Enums\UserRole;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class StoreUserRequest extends FormRequest
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
            'name' => ['required', 'string', 'min:3', 'max:255'],
            'email' => ['required', 'string', 'email', 'unique:users,email'],
            'password' => ['required', 'string', 'min:8'],
            'role' => ['required', 'in:' . implode(',', array_column(UserRole::cases(), 'value'))],
            'is_active' => ['nullable', 'boolean'],
            'business_id' => ['nullable', 'integer', 'exists:businesses,id'],
            'outlet_id' => ['nullable', 'integer', 'exists:outlets,id']
        ];
    }

    public function messages(): array
    {
        return [
            'name.required' => 'Name is required',
            'name.min' => 'Name must be at least 3 characters',
            'name.max' => 'Name must not exceed 255 characters',
            'email.required' => 'Email is required',
            'email.email' => 'Email format is invalid',
            'email.unique' => 'Email is already taken',
            'password.required' => 'Password is required',
            'password.min' => 'Password must be at least 8 characters',
            'role.required' => 'Role is required',
            'role.in' => 'Role must be one of: ' . implode(', ', array_column(UserRole::cases(), 'value')),
            'business_id.integer' => 'Business ID must be a number',
            'business_id.exists' => 'Business not found',
            'outlet_id.integer' => 'Outlet ID must be a number',
            'outlet_id.exists' => 'Outlet not found',
        ];
    }
}
