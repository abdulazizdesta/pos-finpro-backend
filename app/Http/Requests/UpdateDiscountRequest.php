<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateDiscountRequest extends FormRequest
{
    public function authorize(): bool { return true; }

    public function rules(): array
    {
        return [
            'code'         => ['sometimes', 'string', 'max:50', Rule::unique('discounts', 'code')->ignore($this->route('discount'))],
            'name'         => ['sometimes', 'nullable', 'string', 'max:100'],
            'type'         => ['sometimes', 'in:percentage,fixed'],
            'value'        => ['sometimes', 'integer', 'min:1'],
            'min_purchase' => ['sometimes', 'nullable', 'integer', 'min:0'],
            'max_uses'     => ['sometimes', 'nullable', 'integer', 'min:1'],
            'valid_from'   => ['sometimes', 'nullable', 'date'],
            'valid_until'  => ['sometimes', 'nullable', 'date', 'after_or_equal:valid_from'],
            'is_active'    => ['sometimes', 'boolean'],
        ];
    }

    public function messages(): array
    {
        return [
            'code.unique'                => 'Discount code already exists',
            'type.in'                    => 'Discount type must be percentage or fixed',
            'value.integer'              => 'Discount value must be an integer',
            'value.min'                  => 'Discount value must be at least 1',
            'valid_until.after_or_equal' => 'Valid until must be after or equal to valid from',
        ];
    }
}
