<?php

namespace App\Http\Requests\Auth;

use Illuminate\Foundation\Http\FormRequest;

class RegisterRequest extends FormRequest
{
    public function authorize(): bool { return true; }

    public function rules(): array
    {
        return [
            'business_name'  => ['required', 'string', 'min:3', 'max:150'],
            'owner_name'     => ['required', 'string', 'min:3', 'max:100'],
            'email'          => ['required', 'email', 'max:100', 'unique:users,email'],
            'password'       => ['required', 'string', 'min:8', 'confirmed'],
        ];
    }

    public function messages(): array
    {
        return [
            'business_name.required'  => 'Nama bisnis wajib diisi',
            'business_name.min'       => 'Nama bisnis minimal 3 karakter',
            'owner_name.required'     => 'Nama owner wajib diisi',
            'email.required'          => 'Email wajib diisi',
            'email.email'             => 'Format email tidak valid',
            'email.unique'            => 'Email sudah terdaftar',
            'password.required'       => 'Password wajib diisi',
            'password.min'            => 'Password minimal 8 karakter',
            'password.confirmed'      => 'Konfirmasi password tidak cocok',
        ];
    }
}
