<?php

namespace App\Http\Requests\Kmeans;

use Illuminate\Foundation\Http\FormRequest;

class StoreKmeansRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'awal' => [
                'required',
                'date',
            ],

            'akhir' => [
                'required',
                'date',
                'after_or_equal:awal',
            ],
        ];
    }

    public function messages(): array
    {
        return [
            'awal.required' => 'Periode awal wajib diisi.',

            'awal.date' => 'Periode awal tidak valid.',

            'akhir.required' => 'Periode akhir wajib diisi.',

            'akhir.date' => 'Periode akhir tidak valid.',

            'akhir.after_or_equal' => 'Periode akhir tidak boleh sebelum periode awal.',
        ];
    }
}
