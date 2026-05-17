<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreOutletRequest extends FormRequest
{
    public function authorize(): bool { return true; }

    public function rules(): array
    {
        return [
            'name'      => ['required', 'string', 'max:100'],
            'code'      => ['required', 'string', 'max:10', 'unique:outlets,code', 'alpha_num'],
            'phone'     => ['nullable', 'string', 'max:20'],
            'address'   => ['nullable', 'string'],
            'is_active' => ['nullable', 'boolean'],
        ];
    }

    public function messages(): array
    {
        return [
            'name.required'     => 'Outlet name is required',
            'name.max'          => 'Outlet name must not exceed 100 characters',
            'code.required'     => 'Outlet code is required',
            'code.unique'       => 'Outlet code already exists',
            'code.alpha_num'    => 'Outlet code must be alphanumeric',
            'code.max'          => 'Outlet code must not exceed 10 characters',
        ];
    }
}
