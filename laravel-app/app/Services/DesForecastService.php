<?php

namespace App\Services;

use App\Models\DetailKmeans;
use App\Models\HasilDes;
use App\Models\HasilKmeans;
use App\Models\ParameterDes;
use App\Models\Produk;
use App\Models\ProsesDes;
use App\Models\User;
use Carbon\CarbonImmutable;
use DomainException;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use LogicException;
use UnexpectedValueException;

final class DesForecastService
{
    private const VALID_CLUSTERS = [1, 2, 3];

    public function __construct(
        private readonly DesFastApiService $fastApiService,
        private readonly DesStockService $stockService,
        private readonly DesMonthlySalesService $monthlySalesService,
        private readonly DesForecastScopeService $scopeService
    ) {}

    public function createProcess(
        User $user,
        int $hasilKmeansId,
        int $cluster,
        ?int $produkId
    ): ProsesDes {
        $this->assertValidCluster($cluster);

        $hasilKmeans = HasilKmeans::query()
            ->with('detail.produk')
            ->find($hasilKmeansId);

        if (! $hasilKmeans instanceof HasilKmeans) {
            throw new DomainException(
                'Hasil segmentasi yang dipilih tidak ditemukan.'
            );
        }

        $details = $this->selectedDetails(
            $hasilKmeans,
            $cluster,
            $produkId
        );

        [$start, $end] =
            $this->monthlySalesService
                ->globalMonthlyRange();

        $target = $end
            ->addMonthNoOverflow()
            ->startOfMonth();

        [$details, $parameters] =
            $this->forecastableDetails(
                $details,
                $produkId
            );

        $series = $this->monthlySalesService
            ->buildMonthlySeriesBatch(
                $details,
                $start,
                $end
            );

        $this->assertPositiveSales(
            $details,
            $series
        );

        $payload = $this->forecastPayload(
            $details,
            $parameters,
            $series
        );

        $analyses = $this->fastApiService
            ->forecast($payload);

        $analysisByProduct =
            $this->validatedAnalyses(
                $analyses,
                $details
            );

        return $this->storeProcess(
            $user,
            $hasilKmeans,
            $cluster,
            $start,
            $end,
            $target,
            $details,
            $analysisByProduct,
            $produkId
        );
    }

    private function assertValidCluster(
        int $cluster
    ): void {
        if (! in_array(
            $cluster,
            self::VALID_CLUSTERS,
            true
        )) {
            throw new InvalidArgumentException(
                'Kelompok produk yang dipilih tidak valid.'
            );
        }
    }

    /**
     * @return Collection<int, DetailKmeans>
     */
    private function selectedDetails(
        HasilKmeans $hasilKmeans,
        int $cluster,
        ?int $produkId = null
    ): Collection {
        $details = $hasilKmeans->detail
            ->filter(
                fn (
                    DetailKmeans $detail
                ): bool => $detail->cluster === $cluster
                    && $detail->produk !== null
                    && ! $detail->produk->trashed()
            )
            ->sortBy('produk_id')
            ->values();

        if ($produkId !== null) {
            $details = $details
                ->filter(
                    fn (
                        DetailKmeans $detail
                    ): bool => (int) $detail->produk_id
                        === $produkId
                )
                ->values();
        }

        if ($details->isEmpty()) {
            throw new DomainException(
                $produkId === null
                    ? 'Tidak ada produk aktif pada kelompok yang dipilih.'
                    : 'Produk tidak termasuk dalam hasil segmentasi '
                        .'dan kelompok yang dipilih.'
            );
        }

        return $details;
    }

    /**
     * @param  Collection<int, DetailKmeans>  $details
     * @return array{
     *     0:Collection<int, DetailKmeans>,
     *     1:Collection<int, ParameterDes>
     * }
     */
    private function forecastableDetails(
        Collection $details,
        ?int $produkId = null
    ): array {
        $parameters = ParameterDes::query()
            ->whereIn(
                'produk_id',
                $details->pluck('produk_id')
            )
            ->get()
            ->keyBy('produk_id');

        $forecastable = $details
            ->filter(
                fn (
                    DetailKmeans $detail
                ): bool => $parameters->has(
                    (int) $detail->produk_id
                )
            )
            ->values();

        if ($forecastable->isEmpty()) {
            throw new DomainException(
                $produkId === null
                    ? 'Peramalan tidak dapat diproses karena tidak ada '
                        .'produk pada kelompok ini yang memiliki parameter DES.'
                    : 'Produk yang dipilih belum memiliki parameter DES. '
                        .'Lakukan penentuan parameter terlebih dahulu.'
            );
        }

        return [
            $forecastable,
            $parameters,
        ];
    }

    /**
     * @param  Collection<int, DetailKmeans>  $details
     * @param  array<int, list<array{periode:string,aktual:int}>>  $series
     */
    private function assertPositiveSales(
        Collection $details,
        array $series
    ): void {
        $invalid = $details->filter(
            function (
                DetailKmeans $detail
            ) use ($series): bool {
                $productSeries = $series[
                    (int) $detail->produk_id
                ] ?? [];

                return ! collect(
                    $productSeries
                )->contains(
                    fn (array $item): bool => $item['aktual'] > 0
                );
            }
        );

        if ($invalid->isEmpty()) {
            return;
        }

        throw new DomainException(
            "Peramalan tidak dapat diproses karena produk berikut tidak memiliki data penjualan positif:\n"
            .$this->productNames($invalid)->join("\n")
        );
    }

    /**
     * @param  Collection<int, DetailKmeans>  $details
     * @param  Collection<int, ParameterDes>  $parameters
     * @param  array<int, list<array{periode:string,aktual:int}>>  $series
     * @return list<array{
     *     produk_id:int,
     *     alpha:float,
     *     series:list<array{periode:string,aktual:int}>
     * }>
     */
    private function forecastPayload(
        Collection $details,
        Collection $parameters,
        array $series
    ): array {
        return $details
            ->map(
                function (
                    DetailKmeans $detail
                ) use (
                    $parameters,
                    $series
                ): array {
                    $productId =
                        (int) $detail->produk_id;

                    $parameter = $parameters->get(
                        $productId
                    );

                    if (! $parameter instanceof ParameterDes) {
                        throw new LogicException(
                            'Parameter DES produk tidak ditemukan.'
                        );
                    }

                    return [
                        'produk_id' => $productId,
                        'alpha' => (float)
                            $parameter->alpha,
                        'series' => $series[
                            $productId
                        ],
                    ];
                }
            )
            ->values()
            ->all();
    }

    /**
     * @param  list<array{
     *     produk_id:int,
     *     prediksi:float
     * }>  $analyses
     * @param  Collection<int, DetailKmeans>  $details
     * @return Collection<int, array{
     *     produk_id:int,
     *     prediksi:float
     * }>
     */
    private function validatedAnalyses(
        array $analyses,
        Collection $details
    ): Collection {
        $indexed = collect($analyses)
            ->keyBy(
                fn (array $analysis): int => (int) $analysis['produk_id']
            );

        if ($indexed->count() !== $details->count()) {
            throw new UnexpectedValueException(
                'Jumlah hasil prediksi tidak sesuai dengan jumlah produk.'
            );
        }

        return $indexed;
    }

    /**
     * @param  Collection<int, DetailKmeans>  $details
     * @param  Collection<int, array{
     *     produk_id:int,
     *     prediksi:float
     * }>  $analysisByProduct
     */
    private function storeProcess(
        User $user,
        HasilKmeans $hasilKmeans,
        int $cluster,
        CarbonImmutable $start,
        CarbonImmutable $end,
        CarbonImmutable $target,
        Collection $details,
        Collection $analysisByProduct,
        ?int $produkId
    ): ProsesDes {
        return DB::transaction(
            function () use (
                $user,
                $hasilKmeans,
                $cluster,
                $start,
                $end,
                $target,
                $details,
                $analysisByProduct,
                $produkId
            ): ProsesDes {
                $products = Produk::query()
                    ->whereIn(
                        'id',
                        $details->pluck('produk_id')
                    )
                    ->lockForUpdate()
                    ->get()
                    ->keyBy('id');

                if ($products->count() !== $details->count()) {
                    throw new DomainException(
                        'Salah satu produk sudah tidak tersedia.'
                    );
                }

                $process = ProsesDes::query()->create([
                    'user_id' => $user->id,
                    'kmeans_id' => $hasilKmeans->id,
                    'cluster' => $cluster,
                    'data_awal' => $start->toDateString(),
                    'data_akhir' => $end->toDateString(),
                    'target' => $target->toDateString(),
                ]);

                $rows = $details
                    ->map(
                        fn (
                            DetailKmeans $detail
                        ): array => $this->resultRow(
                            $process,
                            $detail,
                            $products,
                            $analysisByProduct,
                            $target
                        )
                    )
                    ->values()
                    ->all();

                HasilDes::query()->insert($rows);

                $process = $process
                    ->loadMissing([
                        'user',
                        'hasilKmeans',
                    ])
                    ->loadCount('hasilDes');

                /*
                 * Scope disimpan sebagai metadata sidecar di storage,
                 * bukan sebagai kolom baru pada proses_des. Penulisan
                 * dilakukan sebelum transaksi database selesai agar
                 * kegagalan metadata membatalkan proses DES.
                 */
                $this->scopeService->remember(
                    $process,
                    $produkId
                );

                return $process;
            },
            3
        );
    }

    /**
     * @param  Collection<int, Produk>  $products
     * @param  Collection<int, array{
     *     produk_id:int,
     *     prediksi:float
     * }>  $analysisByProduct
     * @return array<string, mixed>
     */
    private function resultRow(
        ProsesDes $process,
        DetailKmeans $detail,
        Collection $products,
        Collection $analysisByProduct,
        CarbonImmutable $target
    ): array {
        $productId = (int) $detail->produk_id;
        $product = $products->get($productId);
        $analysis = $analysisByProduct->get($productId);

        if (! $product instanceof Produk) {
            throw new UnexpectedValueException(
                'Produk hasil prediksi tidak ditemukan.'
            );
        }

        if (! is_array($analysis)) {
            throw new UnexpectedValueException(
                'Hasil prediksi salah satu produk tidak ditemukan.'
            );
        }

        $prediction = $analysis['prediksi'] ?? null;

        if (
            (
                ! is_int($prediction)
                && ! is_float($prediction)
            )
            || ! is_finite((float) $prediction)
            || $prediction < 0
        ) {
            throw new UnexpectedValueException(
                'Nilai prediksi DES tidak valid.'
            );
        }

        return [
            'proses_id' => $process->id,
            'produk_id' => $productId,
            'target' => $target->toDateString(),
            'prediksi' => $prediction,
            ...$this->stockService->snapshot(
                $product,
                (float) $prediction
            ),
        ];
    }

    /**
     * @param  Collection<int, DetailKmeans>  $details
     * @return Collection<int, string>
     */
    private function productNames(
        Collection $details
    ): Collection {
        return $details
            ->map(
                fn (
                    DetailKmeans $detail
                ): string => sprintf(
                    '%s — %s',
                    $detail->produk?->kode ?? '-',
                    $detail->produk?->nama
                        ?? 'Produk tidak diketahui'
                )
            )
            ->values();
    }
}
