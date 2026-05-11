<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class BulkImportProductRequest extends FormRequest
{
    public function authorize(): bool { return true; }

    public function rules(): array
    {
        return [
            'file' => ['required', 'file', 'mimes:csv,txt', 'max:2048'],
        ];
    }

    public function messages(): array
    {
        return [
            'file.required' => 'CSV file is required',
            'file.file'     => 'Upload must be a file',
            'file.mimes'    => 'File must be a CSV',
            'file.max'      => 'File size must not exceed 2MB',
        ];
    }
}