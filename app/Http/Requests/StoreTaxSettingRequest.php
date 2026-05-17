<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreTaxSettingRequest extends FormRequest
{
    public function authorize(): bool { return true; }

    public function rules(): array
    {
        return [
            'name'      => ['required', 'string', 'max:50'],
            'rate'      => ['required', 'numeric', 'min:0', 'max:100'],
            'is_active' => ['nullable', 'boolean'],
        ];
    }

    public function messages(): array
    {
        return [
            'name.required'  => 'Tax name is required',
            'name.max'       => 'Tax name must not exceed 50 characters',
            'rate.required'  => 'Tax rate is required',
            'rate.numeric'   => 'Tax rate must be a number',
            'rate.min'       => 'Tax rate must be at least 0',
            'rate.max'       => 'Tax rate must not exceed 100',
        ];
    }
}
