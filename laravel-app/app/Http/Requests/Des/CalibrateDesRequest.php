<?php

namespace App\Http\Requests\Des;

use Illuminate\Foundation\Http\FormRequest;

final class CalibrateDesRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'test_months' => [
                'required',
                'integer',
                'min:1',
                'max:120',
            ],
        ];
    }

    public function messages(): array
    {
        return [
            'test_months.required' => 'Jumlah bulan test wajib diisi.',
            'test_months.integer' => 'Jumlah bulan test harus berupa bilangan bulat.',
            'test_months.min' => 'Jumlah bulan test minimal 1 bulan.',
            'test_months.max' => 'Jumlah bulan test maksimal 120 bulan.',
        ];
    }
}
