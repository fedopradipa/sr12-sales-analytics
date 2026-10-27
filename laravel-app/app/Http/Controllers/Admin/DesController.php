<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Des\CalibrateDesRequest;
use App\Http\Requests\Des\StoreDesRequest;
use App\Models\HasilDes;
use App\Models\ProsesDes;
use App\Models\User;
use App\Services\DesCalibrationResultService;
use App\Services\DesCalibrationService;
use App\Services\DesForecastScopeService;
use App\Services\DesForecastService;
use App\Services\DesStockService;
use App\Services\DesViewDataService;
use DomainException;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\RequestException;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;
use InvalidArgumentException;
use UnexpectedValueException;

final class DesController extends Controller
{
    public function __construct(
        private readonly DesForecastService $forecastService,
        private readonly DesViewDataService $viewDataService,
        private readonly DesStockService $stockService,
        private readonly DesForecastScopeService $scopeService,
        private readonly DesCalibrationResultService $calibrationResultService
    ) {}

    public function index(): View
    {
        return view('admin.des.index', [
            'daftarProses' => $this->viewDataService
                ->paginateProcesses(),
            'selectionOptions' => $this->viewDataService
                ->selectionOptions(),
            'predictionContext' => $this->viewDataService
                ->predictionContext(),
        ]);
    }

    public function calibration(): View
    {
        return view('admin.des.calibration', [
            'predictionContext' => $this->viewDataService
                ->predictionContext(),
            'activeParameters' => $this->viewDataService
                ->activeParameters(),
            'calibrationResult' => $this->calibrationResultService
                ->latest(),
        ]);
    }

    public function calibrate(
        CalibrateDesRequest $request,
        DesCalibrationService $calibrationService
    ): RedirectResponse {
        try {
            $summary = $calibrationService->calibrate(
                (int) $request->validated(
                    'test_months'
                )
            );
        } catch (
            DomainException|InvalidArgumentException
            $exception
        ) {
            return back()
                ->withInput()
                ->with(
                    'error',
                    $exception->getMessage()
                );
        } catch (
            ConnectionException
            |RequestException
            |UnexpectedValueException
            $exception
        ) {
            report($exception);

            return back()
                ->withInput()
                ->with(
                    'error',
                    'Penentuan parameter gagal karena layanan '
                    .'analitik tidak dapat memproses data.'
                );
        }

        $averageWape = number_format(
            (float) $summary['average_wape'],
            4,
            ',',
            '.'
        );

        return redirect()
            ->route('admin.kalibrasi-parameter.index')
            ->with(
                'success',
                sprintf(
                    'Penentuan parameter selesai. %d produk '
                    .'diperbarui, %d produk dilewati. '
                    .'WAPE test rata-rata %s%% '
                    .'(test %d bulan).',
                    (int) $summary['updated'],
                    (int) $summary['skipped'],
                    $averageWape,
                    (int) $summary['test_months']
                )
            );
    }

    public function create(): RedirectResponse
    {
        return redirect()->route(
            'admin.des.index',
            ['open_modal' => 1]
        );
    }

    public function store(
        StoreDesRequest $request
    ): RedirectResponse {
        $user = $request->user();
        abort_unless($user instanceof User, 403);
        $validated = $request->validated();

        $produkId = isset($validated['produk_id'])
            && $validated['produk_id'] !== ''
                ? (int) $validated['produk_id']
                : null;

        try {
            $process = $this->forecastService
                ->createProcess(
                    user: $user,
                    hasilKmeansId: (int)
                        $validated['kmeans_id'],
                    cluster: (int)
                        $validated['cluster'],
                    produkId: $produkId
                );
        } catch (
            DomainException|
            InvalidArgumentException $exception
        ) {
            return back()
                ->withInput()
                ->with(
                    'error',
                    $exception->getMessage()
                );
        } catch (
            ConnectionException|
            RequestException|
            UnexpectedValueException $exception
        ) {
            report($exception);

            return back()
                ->withInput()
                ->with(
                    'error',
                    'Layanan analitik tidak dapat memproses '
                    .'peramalan DES Brown. Pastikan Analytics '
                    .'Service sedang aktif.'
                );
        }

        if ($produkId !== null) {
            $flashKey = 'success';
            $message =
                'Peramalan satu produk berhasil diproses.';

            $routeParameters = [
                'prosesDes' => $process,
                'produk_id' => $produkId,
            ];
        } else {
            $coverage = $this->viewDataService
                ->coverage($process);

            if ((int) $coverage['skipped'] > 0) {
                $flashKey = 'warning';
                $message = sprintf(
                    'Peramalan selesai sebagian. %d dari %d produk '
                    .'berhasil diramalkan. %d produk dilewati karena '
                    .'parameter DES belum tersedia.',
                    (int) $coverage['forecasted'],
                    (int) $coverage['total'],
                    (int) $coverage['skipped']
                );
            } else {
                $flashKey = 'success';
                $message = sprintf(
                    'Peramalan selesai. %d dari %d produk berhasil '
                    .'diramalkan.',
                    (int) $coverage['forecasted'],
                    (int) $coverage['total']
                );
            }

            $routeParameters = [
                'prosesDes' => $process,
            ];
        }

        return redirect()
            ->route(
                'admin.des.process.show',
                $routeParameters
            )
            ->with(
                $flashKey,
                $message
            );
    }

    public function showProcess(
        ProsesDes $prosesDes
    ): View {
        $prosesDes = $this->viewDataService
            ->loadProcess($prosesDes);

        $results = $this->viewDataService
            ->processResults($prosesDes);

        $workspace = $results
            ->map(function (HasilDes $hasil): array {
                $produk = $hasil->produk;
                $inventory = $this->stockService
                    ->evaluate($hasil);

                return [
                    'kode' => $produk?->kode ?? '-',
                    'nama' => $produk?->nama
                        ?? 'Produk dihapus',
                    'prediksi' => (float) $hasil->prediksi,
                    'stok' => $inventory['stok'],
                    'stok_min' => $inventory['stok_min'],
                    'estimasi' => $inventory['estimasi'],
                    'status' => $inventory['status'],
                    'status_label' => $inventory[
                        'status_label'
                    ],
                ];
            })
            ->values();

        $coverage = $this->viewDataService
            ->coverage($prosesDes);

        $requestedProductId = (int) request()
            ->query('produk_id', 0);

        $storedScope = $this->scopeService
            ->resolve($prosesDes);

        if (
            $storedScope === null
            && $requestedProductId > 0
            && $results->contains(
                fn (HasilDes $hasil): bool => (int) $hasil->produk_id
                    === $requestedProductId
            )
        ) {
            /*
             * Fallback hanya untuk proses lama yang dibuat sebelum
             * metadata scope persisten tersedia. Proses baru memakai
             * metadata sidecar sehingga riwayat tetap benar tanpa
             * query produk_id dan setelah logout.
             */
            $coverage = [
                'total' => 1,
                'forecasted' => 1,
                'skipped' => 0,
                'status' => 'complete',
                'status_label' => 'Lengkap',
                'skipped_products' => [],
            ];
        }

        return view('admin.des.process', [
            'prosesDes' => $prosesDes,
            'workspace' => $workspace,
            'coverage' => $coverage,
        ]);
    }
}
