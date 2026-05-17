<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreRefundRequest extends FormRequest
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
            'items' => ['required', 'array', 'min:1'],
            'items.*.transaction_item_id' => ['required', 'integer', 'exists:transaction_items,id'],
            'items.*.quantity' => ['required', 'integer', 'min:1'],
            'reason' => ['nullable', 'string', 'max:500']
        ];
    }

    public function messages(): array
    {
        return [
            'items.required' => 'Items are required',
            'items.array' => 'Items must be an array',
            'items.min' => 'At least 1 item must be refunded',
            'items.*.transaction_item_id.required' => 'Transaction item ID is required',
            'items.*.transaction_item_id.integer' => 'Transaction item ID must be an integer',
            'items.*.transaction_item_id.exists' => 'Transaction item not found',
            'items.*.quantity.required' => 'Quantity is required',
            'items.*.quantity.integer' => 'Quantity must be an integer',
            'items.*.quantity.min' => 'Quantity must be at least 1',
            'reason.string' => 'Reason must be a string',
            'reason.max' => 'Reason must not exceed 500 characters',
        ];
    }
}
