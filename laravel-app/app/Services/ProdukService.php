<?php

namespace App\Services;

use App\Models\Produk;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Validation\ValidationException;

class ProdukService
{
    /**
     * @param array{
     *     search?: string|null,
     * } $filters
     */
    public function paginate(
        array $filters,
        int $perPage = 10
    ): LengthAwarePaginator {
        $search = trim(
            (string) ($filters['search'] ?? '')
        );

        return Produk::query()->withExists([
            'detailTransaksi as punya_transaksi',
        ])
            ->when(
                $search !== '',
                function (Builder $query) use ($search): void {
                    $query->where(
                        function (Builder $subQuery) use ($search): void {
                            $subQuery
                                ->where(
                                    'kode',
                                    'like',
                                    "%{$search}%"
                                )
                                ->orWhere(
                                    'nama',
                                    'like',
                                    "%{$search}%"
                                );
                        }
                    );
                }
            )->orderBy('nama')
            ->paginate($perPage)
            ->withQueryString();
    }

    /**
     * @param array{
     *     kode: string,
     *     nama: string,
     *     stok: int|string,
     *     stok_min: int|string
     * } $data
     */
    public function create(array $data): Produk
    {
        return Produk::create([
            'kode' => $data['kode'],
            'nama' => $data['nama'],
            'stok' => (int) $data['stok'],
            'stok_min' => (int) $data['stok_min'],
            'stok_at' => now(),
        ]);
    }

    /**
     * @param array{
     *     kode: string,
     *     nama: string,
     *     stok_min: int|string
     * } $data
     */
    public function update(
        Produk $produk,
        array $data
    ): bool {
        $kodeBaru = (string) $data['kode'];

        if (
            $kodeBaru !== $produk->kode
            && $produk->detailTransaksi()->exists()
        ) {
            throw ValidationException::withMessages([
                'kode' => 'Kode produk tidak dapat diubah karena produk sudah digunakan dalam transaksi.',
            ]);
        }

        return $produk->update([
            'kode' => $kodeBaru,
            'nama' => $data['nama'],
            'stok_min' => (int) $data['stok_min'],
        ]);
    }

    public function updateStock(
        Produk $produk,
        int $stok
    ): bool {
        return $produk->update([
            'stok' => $stok,
            'stok_at' => now(),
        ]);
    }

    public function delete(
        Produk $produk
    ): bool {
        return (bool) $produk->delete();
    }
}
