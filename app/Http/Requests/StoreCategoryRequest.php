<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreCategoryRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name'        => ['required', 'string', 'min:3', 'max:100'],
            'business_id' => [
                auth()->user()->role->value === 'superadmin' ? 'required' : 'nullable',
                'integer',
                'exists:businesses,id'
            ],
        ];
    }

    public function messages(): array
    {
        return [
            'name.required'        => 'Category name is required',
            'name.min'             => 'Category name must be at least 3 characters',
            'name.max'             => 'Category name must not exceed 100 characters',
            'business_id.required' => 'Business is required for superadmin',
            'business_id.exists'   => 'Business not found',
        ];
    }
}