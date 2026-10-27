<?php

namespace App\Services;

use App\Models\DetailKmeans;
use App\Models\ProsesDes;
use Illuminate\Support\Collection;

final class DesProcessCoverageService
{
    public function __construct(
        private readonly DesForecastScopeService $scopeService
    ) {}

    /**
     * @return array{
     *     total:int,
     *     forecasted:int,
     *     skipped:int,
     *     status:string,
     *     status_label:string,
     *     skipped_products:list<array{
     *         produk_id:int,
     *         kode:string,
     *         nama:string,
     *         alasan:string
     *     }>
     * }
     */
    public function summarize(ProsesDes $process): array
    {
        $process->loadMissing([
            'hasilKmeans.detail.produk',
            'hasilDes',
        ]);

        $singleCoverage = $this->singleCoverage($process);

        if ($singleCoverage !== null) {
            return $singleCoverage;
        }

        $details = $process->hasilKmeans?->detail;

        if ($details === null) {
            return $this->resultOnlyCoverage($process);
        }

        $clusterDetails = $this->clusterDetails(
            $process,
            $details
        );

        $resultIds = $process->hasilDes
            ->pluck('produk_id')
            ->map(
                static fn ($id): int => (int) $id
            )
            ->unique();

        $forecasted = $clusterDetails
            ->filter(
                fn (DetailKmeans $detail): bool => $resultIds->contains(
                    (int) $detail->produk_id
                )
            )
            ->count();

        $skippedDetails = $clusterDetails
            ->reject(
                fn (DetailKmeans $detail): bool => $resultIds->contains(
                    (int) $detail->produk_id
                )
            )
            ->values();

        return $this->clusterCoverage(
            total: $clusterDetails->count(),
            forecasted: $forecasted,
            skippedDetails: $skippedDetails
        );
    }

    private function singleCoverage(
        ProsesDes $process
    ): ?array {
        $scope = $this->scopeService
            ->resolve($process);

        if (($scope['mode'] ?? null) !== 'single') {
            return null;
        }

        $productId = (int) (
            $scope['produk_id'] ?? 0
        );

        $hasResult = $process->hasilDes
            ->contains(
                fn ($hasil): bool => (int) $hasil->produk_id
                    === $productId
            );

        if (! $hasResult) {
            return null;
        }

        return [
            'total' => 1,
            'forecasted' => 1,
            'skipped' => 0,
            'status' => 'complete',
            'status_label' => 'Lengkap',
            'skipped_products' => [],
        ];
    }

    private function resultOnlyCoverage(
        ProsesDes $process
    ): array {
        $count = (int) $process->hasilDes->count();

        return [
            'total' => $count,
            'forecasted' => $count,
            'skipped' => 0,
            'status' => 'complete',
            'status_label' => 'Lengkap',
            'skipped_products' => [],
        ];
    }

    /**
     * @param  Collection<int, DetailKmeans>  $details
     * @return Collection<int, DetailKmeans>
     */
    private function clusterDetails(
        ProsesDes $process,
        Collection $details
    ): Collection {
        $processTime = $process->created_at;

        return $details
            ->filter(
                function (DetailKmeans $detail) use (
                    $process,
                    $processTime
                ): bool {
                    if (
                        (int) $detail->cluster
                        !== (int) $process->cluster
                    ) {
                        return false;
                    }

                    $product = $detail->produk;

                    if ($product === null) {
                        return false;
                    }

                    $deletedAt = $product->deleted_at;

                    /*
                     * Produk yang dihapus setelah proses tetap bagian dari
                     * cakupan historis. Produk yang sudah dihapus sebelum
                     * proses tidak dihitung.
                     */
                    return $deletedAt === null
                        || $processTime === null
                        || $deletedAt->greaterThan($processTime);
                }
            )
            ->unique(
                fn (DetailKmeans $detail): int => (int) $detail->produk_id
            )
            ->sortBy(
                fn (DetailKmeans $detail): string => strtolower(
                    (string) ($detail->produk?->kode ?? '')
                )
            )
            ->values();
    }

    /**
     * @param  Collection<int, DetailKmeans>  $skippedDetails
     */
    private function clusterCoverage(
        int $total,
        int $forecasted,
        Collection $skippedDetails
    ): array {
        $skipped = $skippedDetails->count();
        $status = $skipped > 0
            ? 'partial'
            : 'complete';

        return [
            'total' => $total,
            'forecasted' => $forecasted,
            'skipped' => $skipped,
            'status' => $status,
            'status_label' => $status === 'partial'
                ? 'Selesai Sebagian'
                : 'Lengkap',
            'skipped_products' => $skippedDetails
                ->map(
                    static fn (DetailKmeans $detail): array => [
                        'produk_id' => (int) $detail->produk_id,
                        'kode' => (string) (
                            $detail->produk?->kode ?? '-'
                        ),
                        'nama' => (string) (
                            $detail->produk?->nama
                            ?? 'Produk tidak diketahui'
                        ),
                        'alasan' => 'Parameter DES belum tersedia pada saat peramalan.',
                    ]
                )
                ->all(),
        ];
    }
}
