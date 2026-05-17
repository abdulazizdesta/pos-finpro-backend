<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreDiscountRequest extends FormRequest
{
    public function authorize(): bool { return true; }

    public function rules(): array
    {
        return [
            'code'         => ['required', 'string', 'max:50', 'unique:discounts,code'],
            'name'         => ['nullable', 'string', 'max:100'],
            'type'         => ['required', 'in:percentage,fixed'],
            'value'        => ['required', 'integer', 'min:1'],
            'min_purchase' => ['nullable', 'integer', 'min:0'],
            'max_uses'     => ['nullable', 'integer', 'min:1'],
            'valid_from'   => ['nullable', 'date'],
            'valid_until'  => ['nullable', 'date', 'after_or_equal:valid_from'],
            'is_active'    => ['nullable', 'boolean'],
        ];
    }

    public function messages(): array
    {
        return [
            'code.required'              => 'Discount code is required',
            'code.unique'                => 'Discount code already exists',
            'code.max'                   => 'Discount code must not exceed 50 characters',
            'type.required'              => 'Discount type is required',
            'type.in'                    => 'Discount type must be percentage or fixed',
            'value.required'             => 'Discount value is required',
            'value.integer'              => 'Discount value must be an integer',
            'value.min'                  => 'Discount value must be at least 1',
            'valid_until.after_or_equal' => 'Valid until must be after or equal to valid from',
        ];
    }
}
