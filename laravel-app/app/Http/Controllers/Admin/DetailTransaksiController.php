<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\DetailTransaksi\FilterDetailTransaksiRequest;
use App\Http\Requests\DetailTransaksi\ImportDetailTransaksiRequest;
use App\Http\Requests\DetailTransaksi\UpdateDetailTransaksiRequest;
use App\Models\DetailTransaksi;
use App\Services\DetailTransaksiImportService;
use App\Services\DetailTransaksiService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\UploadedFile;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class DetailTransaksiController extends Controller
{
    public function __construct(
        protected DetailTransaksiService $detailTransaksiService,
        protected DetailTransaksiImportService $importService
    ) {}

    public function index(
        FilterDetailTransaksiRequest $request
    ): View {
        $filters = $request->validated();

        $daftarTransaksi =
            $this->detailTransaksiService
                ->paginate($filters);

        $daftarProduk =
            $this->detailTransaksiService
                ->getProductsForFilter();

        return view(
            'admin.detail-transaksi.index',
            compact(
                'daftarTransaksi',
                'daftarProduk'
            )
        );
    }

    public function update(
        UpdateDetailTransaksiRequest $request,
        DetailTransaksi $detailTransaksi
    ): RedirectResponse {
        $this->detailTransaksiService
            ->update(
                $detailTransaksi,
                $request->validated()
            );

        return redirect()
            ->route(
                'admin.transaksi.index'
            )
            ->with(
                'success',
                'Transaksi berhasil diperbarui.'
            );
    }

    public function destroy(
        DetailTransaksi $detailTransaksi
    ): RedirectResponse {
        $this->detailTransaksiService
            ->delete($detailTransaksi);

        return redirect()
            ->route(
                'admin.transaksi.index'
            )
            ->with(
                'success',
                'Transaksi berhasil dihapus.'
            );
    }

    public function destroyAll(): RedirectResponse
    {
        $jumlah =
            $this->detailTransaksiService
                ->deleteAll();

        return redirect()
            ->route(
                'admin.transaksi.index'
            )
            ->with(
                'success',
                $jumlah > 0
                    ? "{$jumlah} transaksi berhasil dihapus."
                    : 'Tidak ada transaksi yang perlu dihapus.'
            );
    }

    public function createImport(): View
    {
        return view(
            'admin.detail-transaksi.import'
        );
    }

    public function storeImport(
        ImportDetailTransaksiRequest $request
    ): RedirectResponse {
        $file = $request->file(
            'file_transaksi'
        );

        abort_unless(
            $file instanceof UploadedFile,
            422
        );

        $summary =
            $this->importService->import(
                file: $file,
                userId: (int) $request
                    ->user()
                    ->getKey()
            );

        return redirect()
            ->route(
                'admin.transaksi.index'
            )
            ->with(
                'success',
                'Data transaksi berhasil diimport.'
            )
            ->with(
                'import_summary',
                $summary
            );
    }

    public function downloadTemplate(): StreamedResponse
    {
        return response()->streamDownload(
            static function (): void {
                $stream = fopen(
                    'php://output',
                    'wb'
                );

                if ($stream === false) {
                    return;
                }

                fwrite(
                    $stream,
                    "\xEF\xBB\xBF"
                );

                fputcsv(
                    $stream,
                    [
                        'No Faktur',
                        'Tanggal',
                        'Kode Produk',
                        'Harga Jual',
                        'Qty',
                        'Nilai Penjualan',
                    ],
                    ';',
                    '"',
                    ''
                );

                fclose($stream);
            },
            'template-import-transaksi.csv',
            [
                'Content-Type' => 'text/csv; charset=UTF-8',
            ]
        );
    }
}
