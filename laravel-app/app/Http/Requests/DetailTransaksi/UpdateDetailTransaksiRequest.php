<?php

namespace App\Http\Requests\DetailTransaksi;

use App\Models\DetailTransaksi;
use Illuminate\Database\Query\Builder;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class UpdateDetailTransaksiRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'faktur' => Str::squish(
                (string) $this->input('faktur')
            ),
        ]);
    }

    public function rules(): array
    {
        $transaksi = $this->route(
            'detailTransaksi'
        );

        $transaksiId = $transaksi
            instanceof DetailTransaksi
                ? $transaksi->getKey()
                : $transaksi;

        return [
            'tanggal' => [
                'required',
                'date',
            ],

            'faktur' => [
                'required',
                'string',
            ],

            'produk_id' => [
                'required',
                'integer',
                'exists:produk,id',

                Rule::unique(
                    'detail_transaksi',
                    'produk_id'
                )
                    ->where(
                        function (
                            Builder $query
                        ): void {
                            $query
                                ->whereDate(
                                    'tanggal',
                                    $this->input(
                                        'tanggal'
                                    )
                                )
                                ->where(
                                    'faktur',
                                    $this->input(
                                        'faktur'
                                    )
                                );
                        }
                    )
                    ->ignore($transaksiId),
            ],

            'qty' => [
                'required',
                'integer',
                'min:1',
            ],

            'harga' => [
                'required',
                'integer',
                'min:0',
            ],
        ];
    }

    public function messages(): array
    {
        return [
            'tanggal.required' => 'Tanggal transaksi wajib diisi.',
            'tanggal.date' => 'Tanggal transaksi tidak valid.',

            'faktur.required' => 'Nomor faktur wajib diisi.',
            'faktur.string' => 'Nomor faktur tidak valid.',

            'produk_id.required' => 'Produk wajib dipilih.',
            'produk_id.integer' => 'Produk tidak valid.',
            'produk_id.exists' => 'Produk tidak ditemukan.',
            'produk_id.unique' => 'Transaksi dengan tanggal, faktur, dan produk tersebut sudah ada.',

            'qty.required' => 'Qty wajib diisi.',
            'qty.integer' => 'Qty harus berupa bilangan bulat.',
            'qty.min' => 'Qty minimal 1.',

            'harga.required' => 'Harga jual wajib diisi.',
            'harga.integer' => 'Harga jual harus berupa bilangan bulat.',
            'harga.min' => 'Harga jual tidak boleh negatif.',
        ];
    }
}
