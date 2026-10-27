<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Kmeans\StoreKmeansRequest;
use App\Models\HasilKmeans;
use App\Models\User;
use App\Services\KmeansService;
use Carbon\CarbonImmutable;
use DomainException;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\RequestException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use InvalidArgumentException;
use UnexpectedValueException;

final class KmeansController extends Controller
{
    public function __construct(
        private readonly KmeansService $kmeansService
    ) {}

    public function index(): View
    {
        return view('admin.kmeans.index', [
            'daftarHasil' => $this->kmeansService->paginateResults(),
            'periodeTersedia' => $this->kmeansService->availablePeriod(),
        ]);
    }

    public function store(
        StoreKmeansRequest $request
    ): RedirectResponse {
        $data = $request->validated();
        $user = $request->user();

        abort_unless($user instanceof User, 403);

        try {
            $hasil = $this->kmeansService->analyze(
                user: $user,
                periodeAwal: CarbonImmutable::parse(
                    $data['awal']
                ),
                periodeAkhir: CarbonImmutable::parse(
                    $data['akhir']
                )
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
                    'Layanan analitik tidak dapat '
                    .'memproses K-Means. Pastikan '
                    .'Analytics Service sedang aktif.'
                );
        }

        return redirect()
            ->route(
                'admin.kmeans.show',
                $hasil
            )
            ->with(
                'success',
                'Proses segmentasi K-Means '
                .'berhasil dilakukan.'
            );
    }

    public function show(
        Request $request,
        HasilKmeans $hasilKmeans
    ): View {
        $openCluster = (int) $request->integer(
            'cluster',
            1
        );

        if (
            ! in_array(
                $openCluster,
                [1, 2, 3],
                true
            )
        ) {
            $openCluster = 1;
        }

        $filters = $request->query();
        $clusterDetailsByCluster = [];

        foreach ([1, 2, 3] as $clusterNumber) {
            $clusterDetailsByCluster[
                $clusterNumber
            ] = $this->kmeansService
                ->paginateClusterDetails(
                    hasilKmeans: $hasilKmeans,
                    cluster: $clusterNumber,
                    filters: $filters,
                    pageName: 'cluster_'
                        .$clusterNumber
                        .'_page'
                );
        }

        return view('admin.kmeans.show', [
            'hasilKmeans' => $this->kmeansService
                ->loadResultHeader($hasilKmeans),
            'openCluster' => $openCluster,
            'clusterCounts' => $this->kmeansService
                ->clusterCounts($hasilKmeans),
            'clusterDetailsByCluster' => $clusterDetailsByCluster,
        ]);
    }
}
