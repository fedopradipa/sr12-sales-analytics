<?php

namespace App\Services;

use App\Models\HasilKmeans;
use App\Models\ProsesDes;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

final class LaporanService
{
    public function __construct(
        private readonly DesProcessCoverageService $coverageService
    ) {}

    public function paginateKmeans(
        int $perPage = 10
    ): LengthAwarePaginator {
        return HasilKmeans::query()
            ->with('user')
            ->withCount('detail')
            ->latest('created_at')
            ->latest('id')
            ->paginate(
                perPage: $perPage,
                pageName: 'page'
            );
    }

    public function paginateDes(
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
            ->paginate(
                perPage: $perPage,
                pageName: 'page'
            );

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

    /**
     * @return array<string,mixed>
     */
    public function coverage(
        ProsesDes $process
    ): array {
        return $this->coverageService
            ->summarize($process);
    }
}
