<?php

namespace Database\Seeders;

use App\Models\Permission;
use Illuminate\Database\Seeder;

class PermissionSeeder extends Seeder
{
    public function run(): void
    {
        $permissions = [
            [
                'code' => 'produk',
                'name' => 'Kelola Produk',
            ],
            [
                'code' => 'transaksi',
                'name' => 'Kelola Transaksi',
            ],
            [
                'code' => 'kmeans',
                'name' => 'Segmentasi Produk',
            ],
            [
                'code' => 'des',
                'name' => 'Peramalan Penjualan',
            ],
            [
                'code' => 'kalibrasi',
                'name' => 'Penentuan Parameter',
            ],
            [
                'code' => 'laporan.segmentasi',
                'name' => 'Laporan Segmentasi',
            ],
            [
                'code' => 'laporan.prediksi',
                'name' => 'Laporan Peramalan',
            ],
        ];

        foreach ($permissions as $permission) {
            Permission::updateOrCreate(
                [
                    'code' => $permission['code'],
                ],
                [
                    'name' => $permission['name'],
                ]
            );
        }
    }
}
