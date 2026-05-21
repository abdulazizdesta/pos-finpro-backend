<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class AdjustStockRequest extends FormRequest
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
            'quantity_change' => ['required', 'integer', 'not_in:0'],
            'notes' => ['required', 'string', 'max:255', 'min:10'],
        ];
    }

    public function messages(): array
    {
        return [
            'quantity_change.required' => 'Quantity change is required',
            'quantity_change.integer' => 'Quantity change must be a number',
            'quantity_change.not_in' => 'Quantity change must not be zero',
            'notes.required' => 'Notes are required for stock adjustment',
            'notes.max' => 'Notes must not exceed 255 characters',
            'notes.min' => 'Notes must being descriptive',
        ];
    }
}
