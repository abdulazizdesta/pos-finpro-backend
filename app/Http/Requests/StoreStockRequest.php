<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreStockRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'product_id' => ['required', 'integer', 'exists:products,id'],
            'outlet_id' => ['required', 'integer', 'exists:outlets,id'],
            'quantity' => ['required', 'integer', 'min:0'],
            'min_threshold' => ['nullable', 'integer', 'min:0'],
        ];
    }

    public function messages(): array
    {
        return [
            'product_id.required' => 'Product is required',
            'product_id.integer' => 'Product ID must be a number',
            'product_id.exists' => 'Product not found',
            'outlet_id.required' => 'Outlet is required',
            'outlet_id.integer' => 'Outlet ID must be a number',
            'outlet_id.exists' => 'Outlet not found',
            'quantity.required' => 'Quantity is required',
            'quantity.integer' => 'Quantity must be a number',
            'quantity.min' => 'Quantity must not be negative',
            'min_threshold.integer' => 'Minimum threshold must be a number',
            'min_threshold.min' => 'Minimum threshold must not be negative',
        ];
    }
}
