<?php

namespace App\Services;

use App\Models\DetailTransaksi;
use App\Models\Produk;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;

class DetailTransaksiService
{
    /**
     * @param array{
     *     search?: string|null,
     *     tanggal_awal?: string|null,
     *     tanggal_akhir?: string|null,
     *     produk_id?: int|string|null
     * } $filters
     */
    public function paginate(
        array $filters,
        int $perPage = 15
    ): LengthAwarePaginator {
        $search = trim(
            (string) ($filters['search'] ?? '')
        );

        $tanggalAwal =
            $filters['tanggal_awal'] ?? null;

        $tanggalAkhir =
            $filters['tanggal_akhir'] ?? null;

        $produkId =
            $filters['produk_id'] ?? null;

        return DetailTransaksi::query()
            ->with([
                'produk:id,kode,nama,deleted_at',
                'user:id,name',
            ])
            ->when(
                $search !== '',
                function (
                    Builder $query
                ) use ($search): void {
                    $query->where(
                        function (
                            Builder $subQuery
                        ) use ($search): void {
                            $subQuery
                                ->where(
                                    'faktur',
                                    'like',
                                    "{$search}%"
                                )
                                ->orWhereHas(
                                    'produk',
                                    function (
                                        Builder $produkQuery
                                    ) use ($search): void {
                                        $produkQuery
                                            ->withTrashed()
                                            ->where(
                                                function (
                                                    Builder $productSearch
                                                ) use ($search): void {
                                                    $productSearch
                                                        ->where(
                                                            'kode',
                                                            'like',
                                                            "{$search}%"
                                                        )
                                                        ->orWhere(
                                                            'nama',
                                                            'like',
                                                            "%{$search}%"
                                                        );
                                                }
                                            );
                                    }
                                );
                        }
                    );
                }
            )
            ->when(
                $tanggalAwal,
                function (
                    Builder $query,
                    string $tanggalAwal
                ): void {
                    $query->whereDate(
                        'tanggal',
                        '>=',
                        $tanggalAwal
                    );
                }
            )
            ->when(
                $tanggalAkhir,
                function (
                    Builder $query,
                    string $tanggalAkhir
                ): void {
                    $query->whereDate(
                        'tanggal',
                        '<=',
                        $tanggalAkhir
                    );
                }
            )
            ->when(
                $produkId !== null
                && $produkId !== '',
                function (
                    Builder $query
                ) use ($produkId): void {
                    $query->where(
                        'produk_id',
                        (int) $produkId
                    );
                }
            )
            ->orderByDesc('tanggal')
            ->orderByDesc('faktur')
            ->orderByDesc('id')
            ->paginate($perPage)
            ->withQueryString();
    }

    public function getProductsForFilter(): Collection
    {
        return Produk::query()
            ->withTrashed()
            ->orderBy('nama')
            ->get([
                'id',
                'kode',
                'nama',
                'deleted_at',
            ]);
    }

    /**
     * @param array{
     *     tanggal: string,
     *     faktur: string,
     *     produk_id: int|string,
     *     qty: int|string,
     *     harga: int|string
     * } $data
     */
    public function update(
        DetailTransaksi $transaksi,
        array $data
    ): bool {
        $qty = (int) $data['qty'];
        $harga = (int) $data['harga'];

        return $transaksi->update([
            'tanggal' => $data['tanggal'],
            'faktur' => $data['faktur'],
            'produk_id' => (int) $data['produk_id'],
            'qty' => $qty,
            'harga' => $harga,
            'subtotal' => $qty * $harga,
        ]);
    }

    public function delete(
        DetailTransaksi $transaksi
    ): bool {
        return (bool) $transaksi->delete();
    }

    public function deleteAll(): int
    {
        return DB::transaction(
            fn (): int => DetailTransaksi::query()->delete()
        );
    }
}
