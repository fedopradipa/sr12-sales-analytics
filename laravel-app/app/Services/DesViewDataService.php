<?php

namespace App\Services;

use App\Models\DetailKmeans;
use App\Models\HasilDes;
use App\Models\HasilKmeans;
use App\Models\Produk;
use App\Models\ProsesDes;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

final class DesViewDataService
{
    public const CLUSTER_LABELS = [
        1 => 'Pergerakan Tinggi',
        2 => 'Pergerakan Sedang',
        3 => 'Pergerakan Rendah',
    ];

    public function __construct(
        private readonly DesMonthlySalesService $monthlySalesService,
        private readonly DesProcessCoverageService $coverageService
    ) {}

    public function paginateProcesses(
        int $perPage = 10
    ): LengthAwarePaginator {
        $paginator = ProsesDes::query()
            ->with([
                'user',
                'hasilKmeans.detail.produk',
                'hasilDes',
            ])
            ->withCount('hasilDes')
            ->latest('created_at')
            ->latest('id')
            ->paginate($perPage);

        $paginator->getCollection()->each(
            function (ProsesDes $process): void {
                $process->setAttribute(
                    'coverage',
                    $this->coverageService
                        ->summarize($process)
                );
            }
        );

        return $paginator;
    }

    /** @param array<string, mixed> $filters */
    public function paginateProcessResults(
        ProsesDes $process,
        array $filters
    ): LengthAwarePaginator {
        $search = trim(
            (string) ($filters['search'] ?? '')
        );

        $sort = (string) (
            $filters['sort'] ?? 'produk'
        );

        $direction = strtolower(
            (string) ($filters['direction'] ?? 'asc')
        ) === 'desc' ? 'desc' : 'asc';

        $perPage = (int) (
            $filters['per_page'] ?? 25
        );

        if (! in_array(
            $perPage,
            [10, 25, 50, 100],
            true
        )) {
            $perPage = 25;
        }

        $query = HasilDes::query()
            ->where(
                'hasil_des.proses_id',
                $process->id
            )
            ->join(
                'produk',
                'produk.id',
                '=',
                'hasil_des.produk_id'
            )
            ->select('hasil_des.*')
            ->with('produk');

        if ($search !== '') {
            $query->where(
                function (
                    Builder $builder
                ) use ($search): void {
                    $builder
                        ->where(
                            'produk.kode',
                            'like',
                            '%'.$search.'%'
                        )
                        ->orWhere(
                            'produk.nama',
                            'like',
                            '%'.$search.'%'
                        );
                }
            );
        }

        match ($sort) {
            'prediksi' => $query->orderBy(
                'hasil_des.prediksi',
                $direction
            ),
            default => $query->orderBy(
                'produk.nama',
                $direction
            ),
        };

        return $query
            ->orderBy('hasil_des.id')
            ->paginate($perPage)
            ->withQueryString();
    }

    public function loadProcess(
        ProsesDes $process
    ): ProsesDes {
        return $process
            ->loadMissing([
                'user',
                'hasilKmeans.detail.produk',
                'hasilDes',
            ])
            ->loadCount('hasilDes');
    }

    /**
     * @return array{
     *     total:int,
     *     forecasted:int,
     *     skipped:int,
     *     status:string,
     *     status_label:string,
     *     skipped_products:list<array<string,mixed>>
     * }
     */
    public function coverage(
        ProsesDes $process
    ): array {
        return $this->coverageService
            ->summarize($process);
    }

    public function loadResult(
        HasilDes $hasilDes
    ): HasilDes {
        return $hasilDes->loadMissing([
            'produk',
            'prosesDes.user',
            'prosesDes.hasilKmeans',
        ]);
    }

    /**
     * @return array{
     *     awal:string,
     *     akhir:string,
     *     target:string,
     *     awal_iso:string,
     *     akhir_iso:string,
     *     jumlah_bulan:int
     * }|null
     */
    public function predictionContext(): ?array
    {
        try {
            [$start, $end] =
                $this->monthlySalesService
                    ->globalMonthlyRange();
        } catch (\DomainException) {
            return null;
        }

        return [
            'awal' => $start
                ->locale('id')
                ->translatedFormat('F Y'),
            'akhir' => $end
                ->locale('id')
                ->translatedFormat('F Y'),
            'target' => $end
                ->addMonthNoOverflow()
                ->locale('id')
                ->translatedFormat('F Y'),
            'awal_iso' => $start->format('Y-m-d'),
            'akhir_iso' => $end->format('Y-m-d'),
            'jumlah_bulan' => $start->diffInMonths($end) + 1,
        ];
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function selectionOptions(): array
    {
        return HasilKmeans::query()
            ->with('detail.produk.parameterDes')
            ->latest('created_at')
            ->latest('id')
            ->get()
            ->map(
                fn (
                    HasilKmeans $hasilKmeans,
                    int $index
                ): array => $this->selectionOption(
                    $hasilKmeans,
                    $index === 0
                )
            )
            ->filter(
                fn (array $option): bool => $option['clusters'] !== []
            )
            ->values()
            ->all();
    }

    /**
     * @return array<string, mixed>
     */
    private function selectionOption(
        HasilKmeans $hasilKmeans,
        bool $latest
    ): array {
        $clusters = $this->clusters(
            $hasilKmeans->detail
        );

        $totalProducts = collect($clusters)
            ->sum(
                fn (array $cluster): int => count($cluster['products'])
            );

        $periodLabel = $this->periodLabel(
            $hasilKmeans
        );

        return [
            'id' => (int) $hasilKmeans->id,
            'periode' => $periodLabel,
            'jumlah_produk' => $totalProducts,
            'waktu_proses' => $hasilKmeans
                ->created_at
                ?->copy()
                ->setTimezone('Asia/Jakarta')
                ->locale('id')
                ->translatedFormat('d M Y, H.i')
                ?? '-',
            'terbaru' => $latest,
            'label' => sprintf(
                'Segmentasi #%d · %s · %d produk',
                (int) $hasilKmeans->id,
                $periodLabel,
                $totalProducts
            ),
            'clusters' => $clusters,
        ];
    }

    /**
     * @param  Collection<int, DetailKmeans>  $details
     * @return list<array<string, mixed>>
     */
    private function clusters(
        Collection $details
    ): array {
        return $details
            ->groupBy('cluster')
            ->map(
                function (
                    Collection $clusterDetails,
                    int|string $cluster
                ): array {
                    $clusterNumber = (int) $cluster;

                    return [
                        'cluster' => $clusterNumber,
                        'label' => self::CLUSTER_LABELS[
                            $clusterNumber
                        ] ?? 'Tidak diketahui',
                        'products' => $clusterDetails
                            ->filter(
                                fn (
                                    DetailKmeans $detail
                                ): bool => $detail->produk !== null
                                    && ! $detail->produk
                                        ->trashed()
                            )
                            ->sortBy(
                                fn (
                                    DetailKmeans $detail
                                ): string => strtolower(
                                    $detail->produk?->nama
                                    ?? ''
                                )
                            )
                            ->map(
                                fn (
                                    DetailKmeans $detail
                                ): array => [
                                    'id' => (int)
                                        $detail->produk_id,
                                    'kode' => $detail
                                        ->produk?->kode
                                        ?? '-',
                                    'nama' => $detail
                                        ->produk?->nama
                                        ?? 'Produk tidak diketahui',
                                    'has_parameter' => $detail->produk
                                        ?->parameterDes
                                        !== null,
                                ]
                            )
                            ->values()
                            ->all(),
                    ];
                }
            )
            ->filter(
                fn (array $cluster): bool => $cluster['products'] !== []
            )
            ->sortBy('cluster')
            ->values()
            ->all();
    }

    /** @return list<array<string, mixed>> */
    public function activeParameters(): array
    {
        return Produk::query()
            ->with('parameterDes')
            ->orderBy('kode')
            ->get(['id', 'kode', 'nama'])
            ->map(
                static function (Produk $produk): array {
                    $parameter = $produk->parameterDes;

                    return [
                        'kode' => (string) $produk->kode,
                        'nama' => (string) $produk->nama,
                        'alpha' => $parameter?->alpha !== null
                            ? (float) $parameter->alpha
                            : null,
                        'jumlah_test' => $parameter?->jumlah_test,
                        'mae_train' => $parameter?->mae_train !== null
                            ? (float) $parameter->mae_train
                            : null,
                        'wape_train' => $parameter?->wape_train !== null
                            ? (float) $parameter->wape_train
                            : null,
                        'mae_test' => $parameter?->mae_test !== null
                            ? (float) $parameter->mae_test
                            : null,
                        'wape_test' => $parameter?->wape_test !== null
                            ? (float) $parameter->wape_test
                            : null,
                        'data_awal' => $parameter?->data_awal,
                        'data_akhir' => $parameter?->data_akhir,
                        'updated_at' => $parameter?->updated_at,
                    ];
                }
            )
            ->values()
            ->all();
    }

    private function periodLabel(
        HasilKmeans $hasilKmeans
    ): string {
        $start = $hasilKmeans->awal->locale('id');
        $end = $hasilKmeans->akhir->locale('id');

        if ($start->year === $end->year) {
            return sprintf(
                '%s–%s %s',
                $start->translatedFormat('M'),
                $end->translatedFormat('M'),
                $end->format('Y')
            );
        }

        return sprintf(
            '%s %s–%s %s',
            $start->translatedFormat('M'),
            $start->format('Y'),
            $end->translatedFormat('M'),
            $end->format('Y')
        );
    }

    /**
     * @return Collection<int, HasilDes>
     */
    public function processResults(
        ProsesDes $process
    ): Collection {
        return HasilDes::query()
            ->where(
                'hasil_des.proses_id',
                $process->id
            )
            ->join(
                'produk',
                'produk.id',
                '=',
                'hasil_des.produk_id'
            )
            ->select('hasil_des.*')
            ->with('produk')
            ->orderBy('produk.nama')
            ->orderBy('hasil_des.id')
            ->get();
    }
}
