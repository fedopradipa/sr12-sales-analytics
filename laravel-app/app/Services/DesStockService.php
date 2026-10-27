<?php

namespace App\Services;

use App\Models\HasilDes;
use App\Models\Produk;
use LogicException;

final class DesStockService
{
    public const STATUS_KRITIS = 'kritis';

    public const STATUS_WASPADA = 'waspada';

    public const STATUS_AMAN = 'aman';

    public const STATUS_BELUM_LENGKAP =
        'belum_lengkap';

    /**
     * @return array{
     *     stok_pred:int|null,
     *     stok_min_pred:int|null,
     *     stok_pred_at:mixed,
     *     estimasi:int|null,
     *     status:string
     * }
     */
    public function snapshot(
        Produk $produk,
        float $prediksi
    ): array {
        $prediksiBulat = max(
            0,
            (int) round($prediksi)
        );

        if ($produk->stok_at === null) {
            return [
                'stok_pred' => null,
                'stok_min_pred' => null,
                'stok_pred_at' => null,
                'estimasi' => null,
                'status' => self::STATUS_BELUM_LENGKAP,
            ];
        }

        $stok = (int) $produk->stok;
        $stokMin = (int) $produk->stok_min;

        $kebutuhan =
            $prediksiBulat + $stokMin;

        $estimasi = max(
            0,
            $kebutuhan - $stok
        );

        if ($stok <= $stokMin) {
            $status = self::STATUS_KRITIS;
        } elseif ($stok < $kebutuhan) {
            $status = self::STATUS_WASPADA;
        } else {
            $status = self::STATUS_AMAN;
        }

        return [
            'stok_pred' => $stok,
            'stok_min_pred' => $stokMin,
            'stok_pred_at' => $produk->stok_at,
            'estimasi' => $estimasi,
            'status' => $status,
        ];
    }

    /**
     * @return array{
     *     prediksi_bulat:int,
     *     stok:int|null,
     *     stok_min:int|null,
     *     stok_at:mixed,
     *     kebutuhan:int|null,
     *     estimasi:int|null,
     *     status:string,
     *     status_label:string
     * }
     */
    public function evaluate(
        HasilDes $hasilDes
    ): array {
        $prediksiBulat = max(
            0,
            (int) round(
                (float) $hasilDes->prediksi
            )
        );

        $status = (string)
            $hasilDes->status;

        if (
            $status
            === self::STATUS_BELUM_LENGKAP
        ) {
            return [
                'prediksi_bulat' => $prediksiBulat,
                'stok' => null,
                'stok_min' => null,
                'stok_at' => null,
                'kebutuhan' => null,
                'estimasi' => null,
                'status' => $status,
                'status_label' => $this->statusLabel($status),
            ];
        }

        if (! in_array(
            $status,
            [
                self::STATUS_KRITIS,
                self::STATUS_WASPADA,
                self::STATUS_AMAN,
            ],
            true
        )) {
            throw new LogicException(
                'Status persediaan hasil prediksi tidak valid.'
            );
        }

        if (
            $hasilDes->stok_pred
                === null
            || $hasilDes
                ->stok_min_pred
                === null
            || $hasilDes
                ->stok_pred_at
                === null
            || $hasilDes->estimasi
                === null
        ) {
            throw new LogicException(
                'Snapshot persediaan hasil prediksi tidak lengkap.'
            );
        }

        $stok =
            (int) $hasilDes
                ->stok_pred;

        $stokMin =
            (int) $hasilDes
                ->stok_min_pred;

        return [
            'prediksi_bulat' => $prediksiBulat,
            'stok' => $stok,
            'stok_min' => $stokMin,
            'stok_at' => $hasilDes
                ->stok_pred_at,
            'kebutuhan' => $prediksiBulat + $stokMin,
            'estimasi' => (int) $hasilDes
                ->estimasi,
            'status' => $status,
            'status_label' => $this->statusLabel($status),
        ];
    }

    private function statusLabel(
        string $status
    ): string {
        return match ($status) {
            self::STATUS_KRITIS => 'Kritis',
            self::STATUS_WASPADA => 'Waspada',
            self::STATUS_AMAN => 'Aman',
            self::STATUS_BELUM_LENGKAP => 'Belum Lengkap',
            default => throw new LogicException(
                'Status persediaan tidak valid.'
            ),
        };
    }
}
