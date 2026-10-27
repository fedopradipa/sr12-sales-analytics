<?php

namespace App\Services;

use App\Models\DetailTransaksi;
use App\Models\HasilKmeans;
use App\Models\Produk;
use App\Models\ProsesDes;
use App\Models\User;

final class DashboardService
{
    /**
     * Permission yang memang mempunyai card Dashboard.
     *
     * Permission laporan tidak masuk ke daftar ini karena
     * modul laporan tidak mempunyai card ringkasan sendiri.
     *
     * @var array<string, string>
     */
    private const CARD_PERMISSIONS = [
        'produk' => 'produk',
        'transaksi' => 'transaksi',
        'segmentasi' => 'kmeans',
        'prediksi' => 'des',
    ];

    public function canAccess(User $user): bool
    {
        if ($user->isAdmin()) {
            return true;
        }

        foreach (self::CARD_PERMISSIONS as $permission) {
            if ($user->hasPermission($permission)) {
                return true;
            }
        }

        return false;
    }

    /**
     * @return array{
     *     cards: array{
     *         produk: bool,
     *         transaksi: bool,
     *         segmentasi: bool,
     *         prediksi: bool,
     *         pengguna: bool
     *     },
     *     jumlahProduk?: int,
     *     totalProdukTerjual?: int,
     *     jumlahSegmentasi?: int,
     *     jumlahPrediksi?: int,
     *     jumlahPengguna?: int
     * }
     */
    public function getDashboardData(User $user): array
    {
        $isAdmin = $user->isAdmin();

        $cards = [
            'produk' => $isAdmin
                || $user->hasPermission('produk'),
            'transaksi' => $isAdmin
                || $user->hasPermission('transaksi'),
            'segmentasi' => $isAdmin
                || $user->hasPermission('kmeans'),
            'prediksi' => $isAdmin
                || $user->hasPermission('des'),
            'pengguna' => $isAdmin,
        ];

        $data = [
            'cards' => $cards,
        ];

        if ($cards['produk']) {
            $data['jumlahProduk'] = Produk::query()
                ->count();
        }

        if ($cards['transaksi']) {
            $data['totalProdukTerjual'] = (int)
                DetailTransaksi::query()
                    ->sum('qty');
        }

        if ($cards['segmentasi']) {
            $data['jumlahSegmentasi'] =
                HasilKmeans::query()
                    ->count();
        }

        if ($cards['prediksi']) {
            $data['jumlahPrediksi'] =
                ProsesDes::query()
                    ->count();
        }

        if ($cards['pengguna']) {
            $data['jumlahPengguna'] =
                User::query()
                    ->count();
        }

        return $data;
    }
}
