<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateOutletRequest extends FormRequest
{
    public function authorize(): bool { return true; }

    public function rules(): array
    {
        return [
            'name'      => ['sometimes', 'string', 'max:100'],
            'code'      => ['sometimes', 'string', 'max:10', 'alpha_num', Rule::unique('outlets', 'code')->ignore($this->route('outlet'))],
            'phone'     => ['sometimes', 'nullable', 'string', 'max:20'],
            'address'   => ['sometimes', 'nullable', 'string'],
            'is_active' => ['sometimes', 'boolean'],
        ];
    }

    public function messages(): array
    {
        return [
            'name.max'        => 'Outlet name must not exceed 100 characters',
            'code.unique'     => 'Outlet code already exists',
            'code.alpha_num'  => 'Outlet code must be alphanumeric',
            'code.max'        => 'Outlet code must not exceed 10 characters',
        ];
    }
}
