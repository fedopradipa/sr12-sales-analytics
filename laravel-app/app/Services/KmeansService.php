<?php

namespace App\Services;

use App\Models\DetailKmeans;
use App\Models\DetailTransaksi;
use App\Models\HasilKmeans;
use App\Models\User;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use DomainException;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

final class KmeansService
{
    public function __construct(
        private readonly AnalyticsService $analyticsService
    ) {}

    public function analyze(
        User $user,
        CarbonInterface $periodeAwal,
        CarbonInterface $periodeAkhir
    ): HasilKmeans {
        $tanggalAwal = $periodeAwal->toDateString();
        $tanggalAkhir = $periodeAkhir->toDateString();

        if ($periodeAwal->gt($periodeAkhir)) {
            throw new InvalidArgumentException(
                'Periode awal tidak boleh melebihi periode akhir.'
            );
        }

        $products = $this->aggregateProducts(
            $tanggalAwal,
            $tanggalAkhir
        );

        if (count($products) < 3) {
            throw new DomainException(
                'Periode analisis harus memiliki transaksi dari minimal 3 produk.'
            );
        }

        $analysis = $this->analyticsService
            ->analyzeKmeans($products);

        return $this->storeResult(
            user: $user,
            periodeAwal: $tanggalAwal,
            periodeAkhir: $tanggalAkhir,
            analysis: $analysis
        );
    }

    public function paginateResults(
        int $perPage = 10
    ): LengthAwarePaginator {
        return HasilKmeans::query()
            ->with('user')
            ->withCount('detail')
            ->latest('created_at')
            ->latest('id')
            ->paginate($perPage);
    }

    /** @return array{awal:string|null,akhir:string|null} */
    public function availablePeriod(): array
    {
        $periodeAwal = DetailTransaksi::query()->min('tanggal');
        $periodeAkhir = DetailTransaksi::query()->max('tanggal');

        return [
            'awal' => is_string($periodeAwal) ? $periodeAwal : null,
            'akhir' => is_string($periodeAkhir) ? $periodeAkhir : null,
        ];
    }

    public function loadResultHeader(
        HasilKmeans $hasilKmeans
    ): HasilKmeans {
        return $hasilKmeans->loadMissing('user');
    }

    /** @return array<int, int> */
    public function clusterCounts(
        HasilKmeans $hasilKmeans
    ): array {
        $counts = DetailKmeans::query()
            ->where('kmeans_id', $hasilKmeans->id)
            ->selectRaw('cluster, COUNT(*) AS jumlah')
            ->groupBy('cluster')
            ->pluck('jumlah', 'cluster');

        return [
            1 => (int) ($counts[1] ?? 0),
            2 => (int) ($counts[2] ?? 0),
            3 => (int) ($counts[3] ?? 0),
        ];
    }

    /** @param array<string, mixed> $filters */
    public function paginateClusterDetails(
        HasilKmeans $hasilKmeans,
        int $cluster,
        array $filters,
        string $pageName = 'page'
    ): LengthAwarePaginator {
        $search = trim((string) ($filters['search'] ?? ''));
        $perPage = (int) ($filters['per_page'] ?? 25);

        if (! in_array($perPage, [10, 25, 50, 100], true)) {
            $perPage = 25;
        }

        return DetailKmeans::query()
            ->where('detail_kmeans.kmeans_id', $hasilKmeans->id)
            ->where('detail_kmeans.cluster', $cluster)
            ->leftJoin(
                'produk',
                'produk.id',
                '=',
                'detail_kmeans.produk_id'
            )
            ->select('detail_kmeans.*')
            ->with('produk')
            ->when($search !== '', function (Builder $query) use ($search): void {
                $query->where(function (Builder $builder) use ($search): void {
                    $builder
                        ->where('produk.kode', 'like', '%'.$search.'%')
                        ->orWhere('produk.nama', 'like', '%'.$search.'%');
                });
            })
            ->orderByDesc('detail_kmeans.omzet')
            ->orderByDesc('detail_kmeans.qty')
            ->orderBy('produk.nama')
            ->paginate(
                perPage: $perPage,
                pageName: $pageName
            )
            ->withQueryString();
    }

    /**
     * @return list<array{
     *     produk_id:int,
     *     recency:int,
     *     frekuensi:int,
     *     qty:int,
     *     omzet:int
     * }>
     */
    private function aggregateProducts(
        string $periodeAwal,
        string $periodeAkhir
    ): array {
        return DetailTransaksi::query()
            ->select('produk_id')
            ->selectRaw('MAX(tanggal) AS tanggal_terakhir')
            ->selectRaw('COUNT(DISTINCT tanggal) AS frekuensi')
            ->selectRaw('SUM(qty) AS qty')
            ->selectRaw('SUM(subtotal) AS omzet')
            ->whereBetween('tanggal', [$periodeAwal, $periodeAkhir])
            ->groupBy('produk_id')
            ->orderBy('produk_id')
            ->get()
            ->map(
                fn (DetailTransaksi $row): array => [
                    'produk_id' => (int) $row->produk_id,
                    'recency' => (int) CarbonImmutable::parse(
                        (string) $row->getAttribute('tanggal_terakhir')
                    )->diffInDays(
                        CarbonImmutable::parse($periodeAkhir)
                    ),
                    'frekuensi' => (int) $row->getAttribute('frekuensi'),
                    'qty' => (int) $row->getAttribute('qty'),
                    'omzet' => (int) $row->getAttribute('omzet'),
                ]
            )
            ->values()
            ->all();
    }

    /** @param array<string, mixed> $analysis */
    private function storeResult(
        User $user,
        string $periodeAwal,
        string $periodeAkhir,
        array $analysis
    ): HasilKmeans {
        return DB::transaction(function () use (
            $user,
            $periodeAwal,
            $periodeAkhir,
            $analysis
        ): HasilKmeans {
            $hasil = HasilKmeans::query()->create([
                'user_id' => $user->id,
                'awal' => $periodeAwal,
                'akhir' => $periodeAkhir,
                'iterasi' => $analysis['iterasi'],
            ]);

            DetailKmeans::query()->insert(array_map(
                static fn (array $result): array => [
                    'kmeans_id' => $hasil->id,
                    'produk_id' => $result['produk_id'],
                    'recency' => $result['recency'],
                    'frekuensi' => $result['frekuensi'],
                    'qty' => $result['qty'],
                    'omzet' => $result['omzet'],
                    'cluster' => $result['cluster'],
                ],
                $analysis['results']
            ));

            return $hasil->load(['user', 'detail.produk']);
        }, 3);
    }
}
