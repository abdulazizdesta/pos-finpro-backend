<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class OpenShiftRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'outlet_id'    => ['required', 'integer', 'exists:outlets,id'],
            'opening_cash' => ['required', 'numeric', 'min:0'],
        ];
    }
}