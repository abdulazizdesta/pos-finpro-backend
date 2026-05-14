<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreTransactionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'shift_id'               => ['required', 'integer', 'exists:shifts,id'],
            'items'                  => ['required', 'array', 'min:1'],
            'items.*.product_id'     => ['required', 'integer', 'exists:products,id'],
            'items.*.variant_id'     => ['nullable', 'integer', 'exists:product_variants,id'],
            'items.*.quantity'       => ['required', 'integer', 'min:1'],
            'discount_codes'         => ['nullable', 'array'],
            'discount_codes.*'       => ['string'],
            'payment_method'         => ['required', 'in:cash,qris,card'],
            'notes'                  => ['nullable', 'string', 'max:500'],
        ];
    }
}
