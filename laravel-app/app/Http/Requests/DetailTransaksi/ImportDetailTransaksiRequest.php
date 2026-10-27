<?php

namespace App\Http\Requests\DetailTransaksi;

use Closure;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\UploadedFile;

class ImportDetailTransaksiRequest extends FormRequest
{
    private const CSV_MIME_TYPES = [
        'text/csv',
        'text/plain',
        'application/csv',
        'application/vnd.ms-excel',
        'text/x-csv',
        'application/octet-stream',
    ];

    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'file_transaksi' => [
                'required',
                'file',
                function (
                    string $attribute,
                    mixed $value,
                    Closure $fail
                ): void {
                    if (
                        ! $value
                            instanceof UploadedFile
                    ) {
                        $fail(
                            'File transaksi tidak valid.'
                        );

                        return;
                    }

                    $extension = strtolower(
                        trim(
                            $value
                                ->getClientOriginalExtension()
                        )
                    );

                    $mimeType = strtolower(
                        trim(
                            (string)
                            $value->getMimeType()
                        )
                    );

                    if (
                        $extension !== 'csv'
                        || ! in_array(
                            $mimeType,
                            self::CSV_MIME_TYPES,
                            true
                        )
                    ) {
                        $fail(
                            'File transaksi harus '
                            .'menggunakan template CSV '
                            .'yang disediakan sistem.'
                        );
                    }
                },
            ],
        ];
    }

    public function messages(): array
    {
        return [
            'file_transaksi.required' => 'File transaksi wajib dipilih.',
            'file_transaksi.file' => 'File transaksi tidak valid.',
        ];
    }
}
