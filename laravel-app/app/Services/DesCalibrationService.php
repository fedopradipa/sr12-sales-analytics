<?php

namespace App\Services;

use App\Models\DetailTransaksi;
use App\Models\ParameterDes;
use App\Models\Produk;
use Carbon\CarbonImmutable;
use DomainException;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use UnexpectedValueException;

final class DesCalibrationService
{
    public function __construct(
        private readonly DesFastApiService $fastApiService,
        private readonly DesCalibrationResultService $resultService
    ) {}

    /**
     * @return array{
     *     updated:int,
     *     skipped:int,
     *     test_months:int,
     *     average_wape:float,
     *     skipped_products:list<array{
     *         produk_id:int,
     *         kode:string,
     *         nama:string,
     *         reason_code:string,
     *         reason:string
     *     }>
     * }
     */
    public function calibrate(int $testMonths): array
    {
        if ($testMonths < 1) {
            throw new InvalidArgumentException(
                'Jumlah bulan test minimal 1 bulan.'
            );
        }

        $activeProducts = Produk::query()
            ->orderBy('id')
            ->get(['id', 'kode', 'nama']);

        if ($activeProducts->isEmpty()) {
            throw new DomainException(
                'Belum ada produk aktif untuk penentuan parameter.'
            );
        }

        $activeIds = $activeProducts
            ->pluck('id')
            ->map(fn ($id): int => (int) $id)
            ->values();

        $rows = DetailTransaksi::query()
            ->whereIn('produk_id', $activeIds)
            ->orderBy('tanggal')
            ->orderBy('id')
            ->get([
                'produk_id',
                'tanggal',
                'qty',
            ]);

        if ($rows->isEmpty()) {
            throw new DomainException(
                'Data transaksi belum tersedia untuk '
                .'penentuan parameter.'
            );
        }

        [$monthly, $firstMonthByProduct, $globalEnd] =
            $this->aggregateMonthly($rows);

        // Seluruh produk memakai periode awal global yang sama.
        // Bulan sebelum transaksi pertama tetap bernilai aktual 0.
        $globalStart = $this->globalStart(
            $firstMonthByProduct
        );

        [$products, $metadata, $skippedProducts] =
            $this->prepareProducts(
                activeProducts: $activeProducts,
                monthly: $monthly,
                firstMonthByProduct: $firstMonthByProduct,
                globalStart: $globalStart,
                globalEnd: $globalEnd,
                testMonths: $testMonths
            );

        if ($products === []) {
            /*
             * Ini hasil valid evaluasi kecukupan data, bukan kegagalan
             * Analytics Service. Parameter lama harus dihapus agar
             * tidak stale ketika tidak ada produk yang masih layak.
             */
            DB::transaction(
                function () use ($activeIds): void {
                    ParameterDes::query()
                        ->whereIn(
                            'produk_id',
                            $activeIds
                        )
                        ->delete();
                }
            );

            $this->resultService->remember([
                'updated' => 0,
                'skipped' => count($skippedProducts),
                'test_months' => $testMonths,
                'average_wape' => 0.0,
                'skipped_products' => $skippedProducts,
            ]);

            throw new DomainException(
                'Tidak ada produk yang memiliki data train/test '
                .'cukup untuk jumlah bulan test tersebut. '
                .'Parameter lama produk aktif telah dihapus.'
            );
        }

        $results = $this->fastApiService->calibrate(
            products: $products,
            testMonths: $testMonths
        );

        $expectedIds = collect($products)
            ->pluck('produk_id')
            ->map(fn ($id): int => (int) $id)
            ->sort()
            ->values()
            ->all();

        $resultIds = collect($results)
            ->pluck('produk_id')
            ->map(fn ($id): int => (int) $id)
            ->sort()
            ->values()
            ->all();

        if ($expectedIds !== $resultIds) {
            throw new UnexpectedValueException(
                'Respons penentuan parameter tidak sesuai dengan '
                .'daftar produk yang dikirim.'
            );
        }

        $activeProductIds = $activeProducts
            ->pluck('id')
            ->map(
                static fn ($id): int => (int) $id
            )
            ->values()
            ->all();

        $skippedProductIds = array_values(
            array_diff(
                $activeProductIds,
                $expectedIds
            )
        );

        $this->persistResults(
            results: $results,
            metadata: $metadata,
            testMonths: $testMonths,
            skippedProductIds: $skippedProductIds
        );

        $averageWape = collect($results)->avg(
            static fn (array $result): float => (float) $result['wape_test']
        );

        $summary = [
            'updated' => count($results),
            'skipped' => count($skippedProducts),
            'test_months' => $testMonths,
            'average_wape' => round(
                (float) $averageWape,
                4
            ),
            'skipped_products' => $skippedProducts,
        ];

        $this->resultService->remember($summary);

        return $summary;
    }

    /**
     * @param  array<int, CarbonImmutable>  $firstMonthByProduct
     */
    private function globalStart(
        array $firstMonthByProduct
    ): CarbonImmutable {
        $globalStart = null;

        foreach ($firstMonthByProduct as $productFirstMonth) {
            if (
                $productFirstMonth instanceof CarbonImmutable
                && (
                    ! $globalStart instanceof CarbonImmutable
                    || $productFirstMonth->lessThan($globalStart)
                )
            ) {
                $globalStart = $productFirstMonth;
            }
        }

        if (! $globalStart instanceof CarbonImmutable) {
            throw new DomainException(
                'Periode awal global data kalibrasi DES tidak ditemukan.'
            );
        }

        return $globalStart;
    }

    /**
     * @param  Collection<int, Produk>  $activeProducts
     * @param  array<int, array<string, int>>  $monthly
     * @param  array<int, CarbonImmutable>  $firstMonthByProduct
     * @return array{
     *     0:list<array<string,mixed>>,
     *     1:array<int,array{data_awal:string,data_akhir:string}>,
     *     2:list<array{
     *         produk_id:int,
     *         kode:string,
     *         nama:string,
     *         reason_code:string,
     *         reason:string
     *     }>
     * }
     */
    private function prepareProducts(
        Collection $activeProducts,
        array $monthly,
        array $firstMonthByProduct,
        CarbonImmutable $globalStart,
        CarbonImmutable $globalEnd,
        int $testMonths
    ): array {
        $products = [];
        $metadata = [];
        $skippedProducts = [];

        foreach ($activeProducts as $product) {
            $productId = (int) $product->getKey();
            $firstMonth = $firstMonthByProduct[$productId] ?? null;

            if (! $firstMonth instanceof CarbonImmutable) {
                $skippedProducts[] = $this->skippedProduct(
                    product: $product,
                    reasonCode: 'no_transactions',
                    reason: 'Belum memiliki data transaksi penjualan.'
                );

                continue;
            }

            $series = $this->buildSeries(
                productId: $productId,
                start: $globalStart,
                end: $globalEnd,
                monthly: $monthly
            );

            // Minimal 2 bulan train: bulan pertama menjadi
            // inisialisasi Brown dan bulan kedua mulai dievaluasi.
            if (count($series) < ($testMonths + 2)) {
                $skippedProducts[] = $this->skippedProduct(
                    product: $product,
                    reasonCode: 'insufficient_periods',
                    reason: sprintf(
                        'Data bulanan tidak mencukupi untuk '
                        .'minimal 2 bulan Train dan %d bulan Test.',
                        $testMonths
                    )
                );

                continue;
            }

            $trainSeries = array_slice(
                $series,
                0,
                count($series) - $testMonths
            );
            $trainEvaluationSeries = array_slice(
                $trainSeries,
                1
            );
            $testSeries = array_slice(
                $series,
                -$testMonths
            );

            $trainActualTotal = array_sum(
                array_map(
                    static fn (array $row): int => (int) $row['aktual'],
                    $trainEvaluationSeries
                )
            );
            $testActualTotal = array_sum(
                array_map(
                    static fn (array $row): int => (int) $row['aktual'],
                    $testSeries
                )
            );

            if (
                $trainActualTotal <= 0
                || $testActualTotal <= 0
            ) {
                if (
                    $trainActualTotal <= 0
                    && $testActualTotal <= 0
                ) {
                    $reasonCode = 'no_train_test_sales';
                    $reason =
                        'Data Train dan Test tidak memiliki '
                        .'penjualan aktual yang dapat dievaluasi.';
                } elseif ($trainActualTotal <= 0) {
                    $reasonCode = 'no_train_sales';
                    $reason =
                        'Data Train tidak memiliki penjualan '
                        .'aktual yang dapat dievaluasi.';
                } else {
                    $reasonCode = 'no_test_sales';
                    $reason =
                        'Data Test tidak memiliki penjualan '
                        .'aktual yang dapat dievaluasi.';
                }

                $skippedProducts[] = $this->skippedProduct(
                    product: $product,
                    reasonCode: $reasonCode,
                    reason: $reason
                );

                continue;
            }

            $products[] = [
                'produk_id' => $productId,
                'series' => $series,
            ];
            $metadata[$productId] = [
                'data_awal' => $series[0]['periode'],
                'data_akhir' => $series[count($series) - 1]['periode'],
            ];
        }

        return [$products, $metadata, $skippedProducts];
    }

    /**
     * @param  list<array<string,mixed>>  $results
     * @param  array<int,array{data_awal:string,data_akhir:string}>  $metadata
     * @param  list<int>  $skippedProductIds
     */
    private function persistResults(
        array $results,
        array $metadata,
        int $testMonths,
        array $skippedProductIds
    ): void {
        DB::transaction(
            function () use (
                $results,
                $metadata,
                $testMonths,
                $skippedProductIds
            ): void {
                if ($skippedProductIds !== []) {
                    ParameterDes::query()
                        ->whereIn('produk_id', $skippedProductIds)
                        ->delete();
                }

                foreach ($results as $result) {
                    $productId = (int) $result['produk_id'];
                    $period = $metadata[$productId] ?? null;

                    if (! is_array($period)) {
                        throw new UnexpectedValueException(
                            'Metadata periode penentuan parameter tidak ditemukan.'
                        );
                    }

                    ParameterDes::query()->updateOrCreate(
                        ['produk_id' => $productId],
                        [
                            'alpha' => number_format(
                                (float) $result['alpha'],
                                2,
                                '.',
                                ''
                            ),
                            'jumlah_test' => $testMonths,
                            'mae_train' => number_format(
                                (float) $result['mae_train'],
                                4,
                                '.',
                                ''
                            ),
                            'wape_train' => number_format(
                                (float) $result['wape_train'],
                                4,
                                '.',
                                ''
                            ),
                            'mae_test' => number_format(
                                (float) $result['mae_test'],
                                4,
                                '.',
                                ''
                            ),
                            'wape_test' => number_format(
                                (float) $result['wape_test'],
                                4,
                                '.',
                                ''
                            ),
                            'data_awal' => $period['data_awal'],
                            'data_akhir' => $period['data_akhir'],
                        ]
                    );
                }
            }
        );
    }

    /**
     * @return array{
     *     produk_id:int,
     *     kode:string,
     *     nama:string,
     *     reason_code:string,
     *     reason:string
     * }
     */
    private function skippedProduct(
        Produk $product,
        string $reasonCode,
        string $reason
    ): array {
        return [
            'produk_id' => (int) $product->getKey(),
            'kode' => (string) $product->kode,
            'nama' => (string) $product->nama,
            'reason_code' => $reasonCode,
            'reason' => $reason,
        ];
    }

    /**
     * @param  Collection<int, DetailTransaksi>  $rows
     * @return array{
     *     0:array<int,array<string,int>>,
     *     1:array<int,CarbonImmutable>,
     *     2:CarbonImmutable
     * }
     */
    private function aggregateMonthly(Collection $rows): array
    {
        $monthly = [];
        $firstMonthByProduct = [];
        $globalEnd = null;

        foreach ($rows as $row) {
            $productId = (int) $row->produk_id;
            $month = CarbonImmutable::parse(
                $row->tanggal
            )->startOfMonth();
            $monthKey = $month->format('Y-m');

            $monthly[$productId][$monthKey] =
                ($monthly[$productId][$monthKey] ?? 0)
                + (int) $row->qty;

            $first = $firstMonthByProduct[$productId] ?? null;
            if (
                ! $first instanceof CarbonImmutable
                || $month->lessThan($first)
            ) {
                $firstMonthByProduct[$productId] = $month;
            }

            if (
                ! $globalEnd instanceof CarbonImmutable
                || $month->greaterThan($globalEnd)
            ) {
                $globalEnd = $month;
            }
        }

        if (! $globalEnd instanceof CarbonImmutable) {
            throw new DomainException(
                'Periode transaksi untuk penentuan parameter tidak ditemukan.'
            );
        }

        return [$monthly, $firstMonthByProduct, $globalEnd];
    }

    /**
     * @param  array<int,array<string,int>>  $monthly
     * @return list<array{periode:string,aktual:int}>
     */
    private function buildSeries(
        int $productId,
        CarbonImmutable $start,
        CarbonImmutable $end,
        array $monthly
    ): array {
        $series = [];
        $cursor = $start;

        while ($cursor->lessThanOrEqualTo($end)) {
            $monthKey = $cursor->format('Y-m');
            $series[] = [
                'periode' => $cursor->format('Y-m-01'),
                'aktual' => (int) (
                    $monthly[$productId][$monthKey] ?? 0
                ),
            ];
            $cursor = $cursor->addMonth();
        }

        return $series;
    }
}
