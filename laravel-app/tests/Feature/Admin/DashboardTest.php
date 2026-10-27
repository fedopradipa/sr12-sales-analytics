<?php

use App\Models\DetailTransaksi;
use App\Models\HasilKmeans;
use App\Models\Permission;
use App\Models\Produk;
use App\Models\ProsesDes;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

function dashboardTestUser(
    string $role = 'admin'
): User {
    static $sequence = 0;

    $sequence++;

    return User::query()->create([
        'name' => 'Dashboard Test User '.$sequence,
        'email' => 'dashboard-test-'
            .$sequence
            .'@example.test',
        'password' => 'password123',
        'role' => $role,
    ]);
}

function dashboardPermission(
    string $code
): Permission {
    return Permission::query()->firstOrCreate(
        ['code' => $code],
        ['name' => ucfirst($code)]
    );
}

function giveDashboardPermissions(
    User $user,
    array $codes
): void {
    $ids = collect($codes)
        ->map(
            fn (string $code): int => dashboardPermission($code)->id
        )
        ->all();

    $user->permissions()->sync($ids);
}

it(
    'mengarahkan guest dashboard ke login',
    function (): void {
        $this
            ->get(route('admin.dashboard'))
            ->assertRedirect(route('login'));
    }
);

it(
    'operator tanpa fitur yang memiliki card tidak mempunyai dashboard',
    function (): void {
        $operator = dashboardTestUser('operator');

        giveDashboardPermissions(
            $operator,
            ['laporan.segmentasi']
        );

        $this
            ->actingAs($operator)
            ->get(route('admin.dashboard'))
            ->assertForbidden();

        $this
            ->actingAs($operator)
            ->get(
                route(
                    'admin.laporan.segmentasi.index'
                )
            )
            ->assertOk()
            ->assertDontSee(
                route('admin.dashboard'),
                false
            );
    }
);

it(
    'operator produk melihat dashboard hanya dengan card produk',
    function (): void {
        $operator = dashboardTestUser('operator');

        giveDashboardPermissions(
            $operator,
            ['produk']
        );

        Produk::query()->create([
            'kode' => 'SR001',
            'nama' => 'Produk Dashboard',
        ]);

        $this
            ->actingAs($operator)
            ->get(route('admin.dashboard'))
            ->assertOk()
            ->assertViewHas('jumlahProduk', 1)
            ->assertSee('Jumlah Produk')
            ->assertDontSee('Total Produk Terjual')
            ->assertDontSee('Jumlah Segmentasi')
            ->assertDontSee('Jumlah Prediksi')
            ->assertDontSee('Jumlah Pengguna');

        $this
            ->actingAs($operator)
            ->get('/')
            ->assertRedirect(
                route('admin.dashboard')
            );
    }
);

it(
    'operator melihat kombinasi card sesuai fitur yang diberikan',
    function (): void {
        $operator = dashboardTestUser('operator');

        giveDashboardPermissions(
            $operator,
            [
                'transaksi',
                'kmeans',
                'des',
                'laporan.prediksi',
            ]
        );

        $produk = Produk::query()->create([
            'kode' => 'SR002',
            'nama' => 'Produk Analitik',
        ]);

        DetailTransaksi::query()->create([
            'user_id' => $operator->id,
            'produk_id' => $produk->id,
            'tanggal' => '2026-01-10',
            'faktur' => 'INV00000002',
            'qty' => 2,
            'harga' => 50000,
            'subtotal' => 100000,
        ]);

        $hasilKmeans =
            HasilKmeans::query()->create([
                'user_id' => $operator->id,
                'awal' => '2026-01-01',
                'akhir' => '2026-01-31',
                'iterasi' => 5,
            ]);

        ProsesDes::query()->create([
            'user_id' => $operator->id,
            'kmeans_id' => $hasilKmeans->id,
            'cluster' => 1,
            'data_awal' => '2026-01-01',
            'data_akhir' => '2026-01-31',
            'target' => '2026-02-01',
        ]);

        $this
            ->actingAs($operator)
            ->get(route('admin.dashboard'))
            ->assertOk()
            ->assertViewHas(
                'totalProdukTerjual',
                2
            )
            ->assertViewHas(
                'jumlahSegmentasi',
                1
            )
            ->assertViewHas(
                'jumlahPrediksi',
                1
            )
            ->assertSee('Total Produk Terjual')
            ->assertSee('Jumlah Segmentasi')
            ->assertSee('Jumlah Prediksi')
            ->assertDontSee('Jumlah Produk')
            ->assertDontSee('Jumlah Pengguna');
    }
);

it(
    'admin tetap melihat seluruh lima card dashboard',
    function (): void {
        $admin = dashboardTestUser('admin');
        $operator = dashboardTestUser('operator');

        $produkA = Produk::query()->create([
            'kode' => 'SR003',
            'nama' => 'Produk A',
        ]);

        $produkB = Produk::query()->create([
            'kode' => 'SR004',
            'nama' => 'Produk B',
        ]);

        DetailTransaksi::query()->create([
            'user_id' => $operator->id,
            'produk_id' => $produkA->id,
            'tanggal' => '2026-01-10',
            'faktur' => 'INV00000003',
            'qty' => 2,
            'harga' => 50000,
            'subtotal' => 100000,
        ]);

        DetailTransaksi::query()->create([
            'user_id' => $operator->id,
            'produk_id' => $produkB->id,
            'tanggal' => '2026-01-10',
            'faktur' => 'INV00000003',
            'qty' => 1,
            'harga' => 75000,
            'subtotal' => 75000,
        ]);

        $hasilKmeans =
            HasilKmeans::query()->create([
                'user_id' => $admin->id,
                'awal' => '2026-01-01',
                'akhir' => '2026-01-31',
                'iterasi' => 5,
            ]);

        ProsesDes::query()->create([
            'user_id' => $admin->id,
            'kmeans_id' => $hasilKmeans->id,
            'cluster' => 1,
            'data_awal' => '2026-01-01',
            'data_akhir' => '2026-01-31',
            'target' => '2026-02-01',
        ]);

        $this
            ->actingAs($admin)
            ->get(route('admin.dashboard'))
            ->assertOk()
            ->assertViewHas('jumlahProduk', 2)
            ->assertViewHas(
                'totalProdukTerjual',
                3
            )
            ->assertViewHas(
                'jumlahSegmentasi',
                1
            )
            ->assertViewHas(
                'jumlahPrediksi',
                1
            )
            ->assertViewHas('jumlahPengguna', 2)
            ->assertSee('Jumlah Produk')
            ->assertSee('Total Produk Terjual')
            ->assertSee('Jumlah Segmentasi')
            ->assertSee('Jumlah Prediksi')
            ->assertSee('Jumlah Pengguna');
    }
);
