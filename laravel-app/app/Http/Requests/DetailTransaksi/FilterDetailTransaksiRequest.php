<?php

namespace App\Http\Requests\DetailTransaksi;

use Illuminate\Foundation\Http\FormRequest;

class FilterDetailTransaksiRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'search' => [
                'nullable',
                'string',
                'max:120',
            ],
            'tanggal_awal' => [
                'nullable',
                'date',
            ],
            'tanggal_akhir' => [
                'nullable',
                'date',
                'after_or_equal:tanggal_awal',
            ],
            'produk_id' => [
                'nullable',
                'integer',
                'exists:produk,id',
            ],
        ];
    }

    public function messages(): array
    {
        return [
            'search.string' => 'Pencarian harus berupa teks.',
            'search.max' => 'Pencarian maksimal 120 karakter.',

            'tanggal_awal.date' => 'Tanggal awal tidak valid.',
            'tanggal_akhir.date' => 'Tanggal akhir tidak valid.',
            'tanggal_akhir.after_or_equal' => 'Tanggal akhir tidak boleh sebelum tanggal awal.',

            'produk_id.integer' => 'Produk tidak valid.',
            'produk_id.exists' => 'Produk tidak ditemukan.',
        ];
    }
}
