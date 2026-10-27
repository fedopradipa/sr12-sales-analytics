<?php

namespace App\Http\Requests\Produk;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;

class StoreProdukRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'kode' => Str::upper(
                Str::squish((string) $this->input('kode'))
            ),
            'nama' => Str::squish(
                (string) $this->input('nama')
            ),
        ]);
    }

    public function rules(): array
    {
        return ['kode' => [
            'required',
            'string',
            'max:20',
            'unique:produk,kode',
        ],
            'nama' => [
                'required',
                'string',
                'max:60',
            ],
            'stok' => [
                'required',
                'integer',
                'min:0',
                'max:4294967295',
            ],
            'stok_min' => [
                'required',
                'integer',
                'min:0',
                'max:4294967295',
            ],
        ];
    }

    public function messages(): array
    {
        return [

            'kode.required' => 'Kode produk wajib diisi.',
            'kode.string' => 'Kode produk harus berupa teks.',
            'kode.max' => 'Kode produk maksimal 20 karakter.',
            'kode.unique' => 'Kode produk sudah digunakan.',

            'nama.required' => 'Nama produk wajib diisi.',
            'nama.string' => 'Nama produk harus berupa teks.',
            'nama.max' => 'Nama produk maksimal 60 karakter.',

            'stok.required' => 'Stok awal wajib diisi.',
            'stok.integer' => 'Stok awal harus berupa bilangan bulat.',
            'stok.min' => 'Stok awal tidak boleh negatif.',
            'stok.max' => 'Stok awal melebihi batas yang diizinkan.',

            'stok_min.required' => 'Stok minimum wajib diisi.',
            'stok_min.integer' => 'Stok minimum harus berupa bilangan bulat.',
            'stok_min.min' => 'Stok minimum tidak boleh negatif.',
            'stok_min.max' => 'Stok minimum melebihi batas yang diizinkan.',
        ];
    }
}
