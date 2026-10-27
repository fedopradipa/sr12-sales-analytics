<?php

use App\Models\HasilDes;
use App\Models\HasilKmeans;
use App\Models\Produk;
use App\Models\ProsesDes;
use App\Models\User;
use App\Services\DesStockService;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

function desInventoryResult(
    int $stok,
    int $stokMin,
    ?string $stokUpdatedAt,
    float $prediksi
): HasilDes {
    $user = User::query()->create([
        'name' => 'Admin Inventory DES',
        'email' => uniqid(
            'des-inventory-',
            true
        ).'@example.test',
        'password' => 'password123',
        'role' => 'admin',
    ]);

    $produk = Produk::query()->create([
        'kode' => strtoupper(
            uniqid('DES', false)
        ),
        'nama' => 'Produk Inventory',
        'stok' => $stok,
        'stok_min' => $stokMin,
        'stok_at' => $stokUpdatedAt,
    ]);

    $kmeans = HasilKmeans::query()->create([
        'user_id' => $user->id,
        'awal' => '2024-01-01',
        'akhir' => '2024-12-31',
        'iterasi' => 2,
    ]);

    $process = ProsesDes::query()->create([
        'user_id' => $user->id,
        'kmeans_id' => $kmeans->id,
        'cluster' => 1,
        'data_awal' => '2024-01-01',
        'data_akhir' => '2024-12-01',
        'target' => '2025-01-01',
    ]);

    $service = app(
        DesStockService::class
    );

    $snapshot = $service->snapshot(
        $produk,
        $prediksi
    );

    return HasilDes::query()->create([
        'proses_id' => $process->id,
        'produk_id' => $produk->id,
        'target' => '2025-01-01',
        'prediksi' => $prediksi,
        ...$snapshot,
    ]);
}

it(
    'menyimpan snapshot kritis dan menghitung estimasi',
    function (): void {
        $hasil = desInventoryResult(
            stok: 15,
            stokMin: 20,
            stokUpdatedAt: '2026-08-09 10:00:00',
            prediksi: 75
        );

        $result = app(
            DesStockService::class
        )->evaluate($hasil);

        expect($hasil->stok_pred)
            ->toBe(15)
            ->and(
                $hasil
                    ->stok_min_pred
            )->toBe(20)
            ->and(
                $hasil->estimasi
            )->toBe(80)
            ->and(
                $hasil->status
            )->toBe('kritis')
            ->and($result['prediksi_bulat'])
            ->toBe(75)
            ->and($result['kebutuhan'])
            ->toBe(95)
            ->and($result['estimasi'])
            ->toBe(80);
    }
);

it(
    'menyimpan status waspada',
    function (): void {
        $hasil = desInventoryResult(
            stok: 50,
            stokMin: 15,
            stokUpdatedAt: '2026-08-09 10:00:00',
            prediksi: 70
        );

        $result = app(
            DesStockService::class
        )->evaluate($hasil);

        expect($result['status'])
            ->toBe('waspada')
            ->and($result['estimasi'])
            ->toBe(35);
    }
);

it(
    'menyimpan status aman tanpa pengadaan',
    function (): void {
        $hasil = desInventoryResult(
            stok: 80,
            stokMin: 15,
            stokUpdatedAt: '2026-08-09 10:00:00',
            prediksi: 61
        );

        $result = app(
            DesStockService::class
        )->evaluate($hasil);

        expect($result['status'])
            ->toBe('aman')
            ->and($result['estimasi'])
            ->toBe(0);
    }
);

it(
    'menyimpan belum lengkap ketika stok belum diperbarui',
    function (): void {
        $hasil = desInventoryResult(
            stok: 0,
            stokMin: 0,
            stokUpdatedAt: null,
            prediksi: 64
        );

        $result = app(
            DesStockService::class
        )->evaluate($hasil);

        expect(
            $hasil->stok_pred
        )->toBeNull()
            ->and(
                $hasil
                    ->stok_min_pred
            )->toBeNull()
            ->and(
                $hasil
                    ->estimasi
            )->toBeNull()
            ->and(
                $hasil->status
            )->toBe('belum_lengkap')
            ->and($result['estimasi'])
            ->toBeNull()
            ->and($result['kebutuhan'])
            ->toBeNull();
    }
);

it(
    'hasil lama tidak berubah ketika master produk diubah',
    function (): void {
        $hasil = desInventoryResult(
            stok: 20,
            stokMin: 10,
            stokUpdatedAt: '2026-08-09 10:00:00',
            prediksi: 50
        );

        $service = app(
            DesStockService::class
        );

        $before = $service->evaluate($hasil);

        $hasil->produk()->update([
            'stok' => 35,
            'stok_min' => 25,
            'stok_at' => '2026-08-10 10:00:00',
        ]);

        $hasil->refresh();

        $after = $service->evaluate($hasil);

        expect($before['stok'])
            ->toBe(20)
            ->and($before['stok_min'])
            ->toBe(10)
            ->and($before['estimasi'])
            ->toBe(40)
            ->and($after['stok'])
            ->toBe(20)
            ->and($after['stok_min'])
            ->toBe(10)
            ->and($after['estimasi'])
            ->toBe(40);
    }
);
