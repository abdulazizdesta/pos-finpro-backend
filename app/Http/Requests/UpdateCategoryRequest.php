<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateCategoryRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => ['sometimes', 'string', 'min:3', 'max:100'],
        ];
    }

    public function messages(): array
    {
        return [
            'name.min' => 'Category name must be at least 3 characters',
            'name.max' => 'Category name must not exceed 100 characters',
        ];
    }
}