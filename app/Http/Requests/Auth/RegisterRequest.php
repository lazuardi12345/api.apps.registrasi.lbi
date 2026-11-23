<?php

namespace App\Http\Requests\Auth;

use Illuminate\Foundation\Http\FormRequest;

class RegisterRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:191'],
            'email' => ['required', 'string', 'email', 'max:191', 'unique:users,email'],
            'password' => ['required', 'string', 'min:6'],
            'phone' => ['nullable', 'string', 'max:50'],
            'identity_number' => ['nullable', 'string', 'max:100'],
            'gender' => ['nullable', 'string', 'in:L,P,male,female,Laki-laki,Perempuan'],
            'birth_place' => ['nullable', 'string', 'max:100'],
            'birth_date' => ['nullable', 'date'],
            'education' => ['nullable', 'string', 'max:100'],
            'occupation' => ['nullable', 'string', 'max:100'],
            'address' => ['nullable', 'string'],
        ];
    }
}
