<?php

namespace App\Services;

use App\Models\DetailKmeans;
use App\Models\DetailTransaksi;
use App\Models\HasilDes;
use App\Models\ProsesDes;
use Carbon\CarbonImmutable;
use DomainException;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use LogicException;

final class DesMonthlySalesService
{
    /**
     * @return array{0:CarbonImmutable,1:CarbonImmutable}
     */
    public function globalMonthlyRange(): array
    {
        $firstDate = DetailTransaksi::query()->min('tanggal');
        $lastDate = DetailTransaksi::query()->max('tanggal');

        if ($firstDate === null || $lastDate === null) {
            throw new DomainException(
                'Data transaksi belum tersedia untuk peramalan DES.'
            );
        }

        return [
            CarbonImmutable::parse($firstDate)->startOfMonth(),
            CarbonImmutable::parse($lastDate)->startOfMonth(),
        ];
    }

    /**
     * @param  Collection<int, DetailKmeans>  $details
     * @return array<int, list<array{periode:string,aktual:int}>>
     */
    public function buildMonthlySeriesBatch(
        Collection $details,
        CarbonImmutable $start,
        CarbonImmutable $end
    ): array {
        $productIds = $details
            ->pluck('produk_id')
            ->map(fn ($id): int => (int) $id)
            ->unique()
            ->values();

        $actual = $this->monthlyTotals(
            $productIds,
            $start,
            $end
        );

        $series = [];

        foreach ($productIds as $productId) {
            $current = $start;

            while ($current->lte($end)) {
                $period = $current->format('Y-m-01');

                $series[$productId][] = [
                    'periode' => $period,
                    'aktual' => $actual[$productId][$period] ?? 0,
                ];

                $current = $current
                    ->addMonthNoOverflow()
                    ->startOfMonth();
            }
        }

        return $series;
    }

    /**
     * @param  Collection<int, HasilDes>  $results
     * @return array<int, list<array{
     *     periode:CarbonImmutable,
     *     aktual:int
     * }>>
     */
    public function historicalSeriesBatch(
        ProsesDes $process,
        Collection $results
    ): array {
        if ($results->isEmpty()) {
            return [];
        }

        $start = CarbonImmutable::parse(
            $process->data_awal
        )->startOfMonth();

        $end = CarbonImmutable::parse(
            $process->data_akhir
        )->startOfMonth();

        $productIds = $results
            ->pluck('produk_id')
            ->map(fn ($id): int => (int) $id)
            ->unique()
            ->values();

        $actual = $this->monthlyTotals(
            $productIds,
            $start,
            $end
        );

        $series = [];

        foreach ($productIds as $productId) {
            $current = $start;

            while ($current->lte($end)) {
                $period = $current->format('Y-m-01');

                $series[$productId][] = [
                    'periode' => $current,
                    'aktual' => $actual[$productId][$period] ?? 0,
                ];

                $current = $current
                    ->addMonthNoOverflow()
                    ->startOfMonth();
            }
        }

        return $series;
    }

    /**
     * @return list<array{
     *     periode:CarbonImmutable,
     *     aktual:int
     * }>
     */
    public function historicalSeries(
        HasilDes $hasilDes
    ): array {
        $hasilDes->loadMissing('prosesDes');

        $process = $hasilDes->prosesDes;

        if (! $process instanceof ProsesDes) {
            throw new DomainException(
                'Proses DES tidak ditemukan.'
            );
        }

        $start = CarbonImmutable::parse(
            $process->data_awal
        )->startOfMonth();

        $end = CarbonImmutable::parse(
            $process->data_akhir
        )->startOfMonth();

        $actual = $this->monthlyTotals(
            collect([(int) $hasilDes->produk_id]),
            $start,
            $end
        );

        $series = [];
        $current = $start;
        $productId = (int) $hasilDes->produk_id;

        while ($current->lte($end)) {
            $period = $current->format('Y-m-01');

            $series[] = [
                'periode' => $current,
                'aktual' => $actual[$productId][$period] ?? 0,
            ];

            $current = $current
                ->addMonthNoOverflow()
                ->startOfMonth();
        }

        return $series;
    }

    /**
     * @param  Collection<int, int>  $productIds
     * @return array<int, array<string, int>>
     */
    private function monthlyTotals(
        Collection $productIds,
        CarbonImmutable $start,
        CarbonImmutable $end
    ): array {
        if ($productIds->isEmpty()) {
            return [];
        }

        $periodExpression =
            $this->monthlyPeriodExpression();

        $rows = DetailTransaksi::query()
            ->select('produk_id')
            ->selectRaw(
                $periodExpression.' AS periode'
            )
            ->selectRaw('SUM(qty) AS aktual')
            ->whereIn('produk_id', $productIds)
            ->whereBetween('tanggal', [
                $start->toDateString(),
                $end->endOfMonth()->toDateString(),
            ])
            ->groupBy('produk_id')
            ->groupByRaw($periodExpression)
            ->orderBy('produk_id')
            ->orderByRaw($periodExpression)
            ->get();

        $actual = [];

        foreach ($rows as $row) {
            $actual[
                (int) $row->produk_id
            ][
                (string) $row->getAttribute('periode')
            ] = (int) $row->getAttribute('aktual');
        }

        return $actual;
    }

    private function monthlyPeriodExpression(): string
    {
        return match (
            DB::connection()->getDriverName()
        ) {
            'mysql',
            'mariadb' => "DATE_FORMAT(tanggal, '%Y-%m-01')",
            'sqlite' => "strftime('%Y-%m-01', tanggal)",
            default => throw new LogicException(
                'Driver database tidak mendukung agregasi periode DES.'
            ),
        };
    }
}
