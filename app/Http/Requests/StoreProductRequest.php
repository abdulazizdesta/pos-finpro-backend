<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreProductRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'category_id' => ['nullable', 'integer', 'exists:categories,id'],
            'business_id' => ['nullable', 'integer', 'exists:businesses,id'],
            'name' => ['required', 'string', 'max:150'],
            'sku' => ['nullable', 'string', 'max:50', 'unique:products,sku'],
            'description' => ['nullable', 'string'],
            'price' => ['required', 'numeric', 'min:0'],
            'cost_price' => ['nullable', 'numeric', 'min:0'],
            'image' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:2048'],
            'has_variants' => ['nullable', 'boolean'],
            'is_active' => ['nullable', 'boolean'],
        ];
    }

    public function messages(): array
    {
        return [
            'category_id.exists' => 'Category not found',
            'business_id.exists' => 'Business not found',
            'business_id.integer' => 'Business ID must be a number',
            'name.required' => 'Product name is required',
            'name.max' => 'Product name must not exceed 150 characters',
            'sku.unique' => 'SKU already exists',
            'sku.max' => 'SKU must not exceed 50 characters',
            'price.required' => 'Price is required',
            'price.numeric' => 'Price must be a number',
            'price.min' => 'Price must be at least 0',
            'cost_price.numeric' => 'Cost price must be a number',
            'cost_price.min' => 'Cost price must be at least 0',
            'image.image' => 'File must be an image',
            'image.mimes' => 'Image must be jpg, jpeg, png, or webp',
            'image.max' => 'Image size must not exceed 2MB',
            'has_variants.boolean' => 'has_variants must be true or false',
            'is_active.boolean' => 'is_active must be true or false',
        ];
    }
}