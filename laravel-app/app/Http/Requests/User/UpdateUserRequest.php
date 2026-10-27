<?php

namespace App\Http\Requests\User;

use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class UpdateUserRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->isAdmin() ?? false;
    }

    public function rules(): array
    {
        $target = $this->route('user');

        return [
            'name' => ['required', 'string', 'max:100'],
            'email' => [
                'required',
                'email',
                'max:100',
                Rule::unique('users', 'email')->ignore(
                    $target instanceof User
                        ? $target->id
                        : null
                ),
            ],
            'password' => [
                'nullable',
                'string',
                'min:8',
                'confirmed',
            ],
            'permissions' => [
                'required',
                'array',
                'min:1',
            ],
            'permissions.*' => [
                'integer',
                'distinct',
                'exists:permissions,id',
            ],
        ];
    }

    public function messages(): array
    {
        return [
            'name.required' => 'Nama operator wajib diisi.',
            'email.required' => 'Email operator wajib diisi.',
            'email.email' => 'Format email operator tidak valid.',
            'email.unique' => 'Email tersebut sudah digunakan.',
            'password.min' => 'Password minimal terdiri dari 8 karakter.',
            'password.confirmed' => 'Konfirmasi password tidak sesuai.',
            'permissions.required' => 'Minimal satu hak akses wajib dipilih.',
            'permissions.min' => 'Minimal satu hak akses wajib dipilih.',
            'permissions.*.exists' => 'Hak akses yang dipilih tidak valid.',
        ];
    }
}
