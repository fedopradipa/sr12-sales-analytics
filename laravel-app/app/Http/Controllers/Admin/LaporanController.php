<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\HasilDes;
use App\Models\HasilKmeans;
use App\Models\ProsesDes;
use App\Services\DesStockService;
use App\Services\LaporanService;
use Dompdf\Dompdf;
use Dompdf\Options;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Str;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

final class LaporanController extends Controller
{
    public function __construct(
        private readonly LaporanService $laporanService,
        private readonly DesStockService $stockService
    ) {}

    public function index(): RedirectResponse
    {
        $user = auth()->user();

        abort_if(
            $user === null,
            403,
            'Anda tidak memiliki hak akses ke fitur ini.'
        );

        if (
            $user->hasPermission(
                'laporan.segmentasi'
            )
        ) {
            return redirect()->route(
                'admin.laporan.segmentasi.index'
            );
        }

        if (
            $user->hasPermission(
                'laporan.prediksi'
            )
        ) {
            return redirect()->route(
                'admin.laporan.peramalan.index'
            );
        }

        abort(
            403,
            'Anda tidak memiliki hak akses ke fitur ini.'
        );
    }

    public function segmentasi(): View
    {
        return view(
            'admin.laporan.segmentasi',
            [
                'daftarKmeans' => $this->laporanService
                    ->paginateKmeans(),
            ]
        );
    }

    public function peramalan(): View
    {
        return view(
            'admin.laporan.peramalan',
            [
                'daftarDes' => $this->laporanService
                    ->paginateDes(),
            ]
        );
    }

    public function downloadKmeans(
        HasilKmeans $hasilKmeans
    ): StreamedResponse {
        $hasilKmeans->loadMissing([
            'user',
            'detail.produk',
        ]);

        $details = $hasilKmeans
            ->detail
            ->sortBy([
                ['cluster', 'asc'],
                ['recency', 'asc'],
                ['produk.nama', 'asc'],
            ])
            ->values();

        return $this->downloadPdf(
            view: 'admin.laporan.pdf.kmeans',
            data: [
                'hasilKmeans' => $hasilKmeans,
                'details' => $details,
                'logoData' => $this->logoData(),
            ],
            filename: sprintf(
                'segmentasi-produk-%d-%s-sd-%s.pdf',
                $hasilKmeans->id,
                $hasilKmeans->awal->format('Y-m-d'),
                $hasilKmeans->akhir->format('Y-m-d')
            )
        );
    }

    public function downloadDesBatch(
        ProsesDes $prosesDes
    ): StreamedResponse {
        $prosesDes->loadMissing([
            'user',
            'hasilKmeans.detail.produk',
            'hasilDes.produk',
        ]);

        $rows = $prosesDes
            ->hasilDes
            ->sortBy(
                fn (HasilDes $hasil): string => strtolower(
                    (string) (
                        $hasil->produk?->nama
                        ?? ''
                    )
                )
            )
            ->values()
            ->map(
                fn (HasilDes $hasil): array => [
                    'hasil' => $hasil,
                    'inventory' => $this->stockService
                        ->evaluate($hasil),
                ]
            );

        return $this->downloadPdf(
            view: 'admin.laporan.pdf.des-batch',
            data: [
                'prosesDes' => $prosesDes,
                'rows' => $rows,
                'coverage' => $this->laporanService
                    ->coverage($prosesDes),
                'logoData' => $this->logoData(),
            ],
            filename: sprintf(
                'prediksi-penjualan-%d-prioritas-%d-%s.pdf',
                $prosesDes->id,
                $prosesDes->cluster,
                $prosesDes->target->format('Y-m')
            )
        );
    }

    public function downloadDes(
        HasilDes $hasilDes
    ): StreamedResponse {
        $hasilDes->loadMissing([
            'produk',
            'prosesDes.user',
            'prosesDes.hasilKmeans',
        ]);

        $productCode = Str::slug(
            $hasilDes->produk?->kode
            ?? 'produk'
        );

        return $this->downloadPdf(
            view: 'admin.laporan.pdf.des-detail',
            data: [
                'hasilDes' => $hasilDes,
                'inventory' => $this->stockService
                    ->evaluate($hasilDes),
                'logoData' => $this->logoData(),
            ],
            filename: sprintf(
                'prediksi-produk-%s-%d.pdf',
                $productCode,
                $hasilDes->id
            )
        );
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function downloadPdf(
        string $view,
        array $data,
        string $filename
    ): StreamedResponse {
        $options = new Options;
        $options->set(
            'defaultFont',
            'DejaVu Sans'
        );
        $options->set(
            'isRemoteEnabled',
            false
        );

        $pdf = new Dompdf($options);

        $pdf->loadHtml(
            view($view, $data)->render()
        );

        $pdf->setPaper(
            'A4',
            'landscape'
        );

        $pdf->render();

        $binary = $pdf->output();

        return response()->streamDownload(
            static function () use ($binary): void {
                echo $binary;
            },
            $filename,
            [
                'Content-Type' => 'application/pdf',
            ]
        );
    }

    private function logoData(): ?string
    {
        $path = public_path(
            'images/logo-sr12.png'
        );

        if (! is_file($path)) {
            return null;
        }

        $contents = file_get_contents($path);

        if ($contents === false) {
            return null;
        }

        return 'data:image/png;base64,'
            .base64_encode($contents);
    }
}
