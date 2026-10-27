<?php

namespace App\Http\Requests\Des;

use Illuminate\Foundation\Http\FormRequest;

class StoreDesRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'kmeans_id' => [
                'required',
                'integer',
                'exists:hasil_kmeans,id',
            ],
            'cluster' => [
                'required',
                'integer',
                'in:1,2,3',
            ],
            'produk_id' => [
                'nullable',
                'integer',
                'min:1',
                'exists:produk,id',
            ],
        ];
    }

    public function messages(): array
    {
        return [
            'kmeans_id.required' => 'Hasil segmentasi wajib dipilih.',
            'kmeans_id.exists' => 'Hasil segmentasi yang dipilih tidak ditemukan.',
            'cluster.required' => 'Kelompok produk wajib dipilih.',
            'cluster.in' => 'Kelompok produk yang dipilih tidak valid.',
            'produk_id.integer' => 'Produk yang dipilih tidak valid.',
            'produk_id.min' => 'Produk yang dipilih tidak valid.',
            'produk_id.exists' => 'Produk yang dipilih tidak ditemukan.',
        ];
    }
}
