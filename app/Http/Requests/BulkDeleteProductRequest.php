<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class BulkDeleteProductRequest extends FormRequest
{
    public function authorize(): bool { return true; }

    public function rules(): array
    {
        return [
            'ids'   => ['required', 'array', 'min:1'],
            'ids.*' => ['required', 'integer', 'exists:products,id'],
        ];
    }

    public function messages(): array
    {
        return [
            'ids.required'   => 'IDs are required',
            'ids.array'      => 'IDs must be an array',
            'ids.min'        => 'At least one ID is required',
            'ids.*.integer'  => 'Each ID must be a number',
            'ids.*.exists'   => 'One or more products not found',
        ];
    }
}