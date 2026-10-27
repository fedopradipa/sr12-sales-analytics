<?php

use App\Models\DetailKmeans;
use App\Models\DetailTransaksi;
use App\Models\HasilDes;
use App\Models\HasilKmeans;
use App\Models\Permission;
use App\Models\Produk;
use App\Models\ProsesDes;
use App\Models\User;
use App\Services\DesForecastScopeService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    File::deleteDirectory(
        storage_path(
            'framework/testing/des-forecast-scopes'
        )
    );
});

function fixedParameterReportDataset(): array
{
    $user = User::query()->create([
        'name' => 'Admin Laporan',
        'email' => 'admin-laporan@example.test',
        'password' => 'password123',
        'role' => 'admin',
    ]);

    $product = Produk::query()->create([
        'kode' => 'LAP001',
        'nama' => 'Produk Laporan',
        'stok' => 15,
        'stok_min' => 20,
        'stok_at' => '2026-08-09 10:00:00',
    ]);

    $kmeans = HasilKmeans::query()->create([
        'user_id' => $user->id,
        'awal' => '2024-01-01',
        'akhir' => '2024-12-31',
        'iterasi' => 2,
    ]);

    DetailKmeans::query()->create([
        'kmeans_id' => $kmeans->id,
        'produk_id' => $product->id,
        'recency' => 0,
        'frekuensi' => 12,
        'qty' => 120,
        'omzet' => 1200000,
        'cluster' => 2,
    ]);

    for ($month = 1; $month <= 12; $month++) {
        DetailTransaksi::query()->create([
            'user_id' => $user->id,
            'produk_id' => $product->id,
            'tanggal' => sprintf(
                '2024-%02d-10',
                $month
            ),
            'faktur' => sprintf(
                'INV-LAP-%02d',
                $month
            ),
            'qty' => 10 + $month,
            'harga' => 10000,
            'subtotal' => (10 + $month) * 10000,
        ]);
    }

    $process = ProsesDes::query()->create([
        'user_id' => $user->id,
        'kmeans_id' => $kmeans->id,
        'cluster' => 2,
        'data_awal' => '2024-01-01',
        'data_akhir' => '2024-12-01',
        'target' => '2025-01-01',
    ]);

    $result = HasilDes::query()->create([
        'proses_id' => $process->id,
        'produk_id' => $product->id,
        'target' => '2025-01-01',
        'prediksi' => 25.5,
        'stok_pred' => 15,
        'stok_min_pred' => 20,
        'stok_pred_at' => '2026-08-09 10:00:00',
        'estimasi' => 31,
        'status' => 'kritis',
    ]);

    return compact(
        'user',
        'product',
        'kmeans',
        'process',
        'result'
    );
}

it('menampilkan halaman laporan segmentasi dan prediksi', function (): void {
    $dataset =
        fixedParameterReportDataset();

    $this->actingAs($dataset['user'])
        ->get(route('admin.laporan.index'))
        ->assertRedirect(
            route(
                'admin.laporan.segmentasi.index'
            )
        );

    $this->actingAs($dataset['user'])
        ->get(
            route(
                'admin.laporan.segmentasi.index'
            )
        )
        ->assertOk()
        ->assertSee('Laporan Segmentasi')
        ->assertSee('Segmentasi #');

    $this->actingAs($dataset['user'])
        ->get(
            route(
                'admin.laporan.peramalan.index'
            )
        )
        ->assertOk()
        ->assertSee('Laporan Peramalan')
        ->assertSee('Stok Perlu Ditambah')
        ->assertSee('Januari 2025');
});

it('mempertahankan cakupan satu produk pada riwayat laporan', function (): void {
    $dataset =
        fixedParameterReportDataset();

    $otherProduct = Produk::query()->create([
        'kode' => 'LAP002',
        'nama' => 'Produk Laporan Lain',
        'stok' => 10,
        'stok_min' => 5,
        'stok_at' => '2026-08-09 10:00:00',
    ]);

    DetailKmeans::query()->create([
        'kmeans_id' => $dataset['kmeans']->id,
        'produk_id' => $otherProduct->id,
        'recency' => 0,
        'frekuensi' => 8,
        'qty' => 80,
        'omzet' => 800000,
        'cluster' => 2,
    ]);

    app(
        DesForecastScopeService::class
    )->remember(
        $dataset['process'],
        $dataset['product']->id
    );

    $this->actingAs($dataset['user'])
        ->get(
            route(
                'admin.laporan.peramalan.index'
            )
        )
        ->assertOk()
        ->assertViewHas(
            'daftarDes',
            function ($paginator) use ($dataset): bool {
                $row = $paginator
                    ->getCollection()
                    ->firstWhere(
                        'id',
                        $dataset['process']->id
                    );

                return $row instanceof ProsesDes
                    && $row->coverage['total'] === 1
                    && $row->coverage['forecasted'] === 1
                    && $row->coverage['skipped'] === 0
                    && $row->coverage['status'] === 'complete';
            }
        );
});

it('mengunduh laporan segmentasi dan prediksi dalam pdf', function (): void {
    $dataset =
        fixedParameterReportDataset();

    $this->actingAs($dataset['user'])
        ->get(
            route(
                'admin.laporan.kmeans.pdf',
                $dataset['kmeans']
            )
        )
        ->assertOk()
        ->assertHeader(
            'content-type',
            'application/pdf'
        )
        ->assertDownload(
            sprintf(
                'segmentasi-produk-%d-2024-01-01-sd-2024-12-31.pdf',
                $dataset['kmeans']->id
            )
        );

    $this->actingAs($dataset['user'])
        ->get(
            route(
                'admin.laporan.des.batch.pdf',
                $dataset['process']
            )
        )
        ->assertOk()
        ->assertHeader(
            'content-type',
            'application/pdf'
        )
        ->assertDownload(
            sprintf(
                'prediksi-penjualan-%d-prioritas-2-2025-01.pdf',
                $dataset['process']->id
            )
        );

    $this->actingAs($dataset['user'])
        ->get(
            route(
                'admin.laporan.des.pdf',
                $dataset['result']
            )
        )
        ->assertOk()
        ->assertHeader(
            'content-type',
            'application/pdf'
        )
        ->assertDownload(
            sprintf(
                'prediksi-produk-lap001-%d.pdf',
                $dataset['result']->id
            )
        );
});

it('memisahkan hak akses laporan segmentasi dan prediksi untuk operator', function (): void {
    $dataset = fixedParameterReportDataset();

    $segmentasiPermission = Permission::query()
        ->firstOrCreate(
            ['code' => 'laporan.segmentasi'],
            ['name' => 'Laporan Segmentasi']
        );

    $prediksiPermission = Permission::query()
        ->firstOrCreate(
            ['code' => 'laporan.prediksi'],
            ['name' => 'Laporan Peramalan']
        );

    $operatorSegmentasi = User::query()->create([
        'name' => 'Operator Segmentasi',
        'email' => 'operator-segmentasi@example.test',
        'password' => 'password123',
        'role' => 'operator',
    ]);

    $operatorSegmentasi->permissions()->sync([
        $segmentasiPermission->id,
    ]);

    $this->actingAs($operatorSegmentasi)
        ->get(route('admin.laporan.index'))
        ->assertRedirect(
            route('admin.laporan.segmentasi.index')
        );

    $this->actingAs($operatorSegmentasi)
        ->get(route('admin.laporan.segmentasi.index'))
        ->assertOk()
        ->assertSee('Laporan Segmentasi')
        ->assertDontSee('Laporan Peramalan');

    $this->actingAs($operatorSegmentasi)
        ->get(route('admin.laporan.peramalan.index'))
        ->assertForbidden();

    $this->actingAs($operatorSegmentasi)
        ->get(
            route(
                'admin.laporan.des.batch.pdf',
                $dataset['process']
            )
        )
        ->assertForbidden();

    $operatorPrediksi = User::query()->create([
        'name' => 'Operator Prediksi',
        'email' => 'operator-prediksi@example.test',
        'password' => 'password123',
        'role' => 'operator',
    ]);

    $operatorPrediksi->permissions()->sync([
        $prediksiPermission->id,
    ]);

    $this->actingAs($operatorPrediksi)
        ->get(route('admin.laporan.index'))
        ->assertRedirect(
            route('admin.laporan.peramalan.index')
        );

    $this->actingAs($operatorPrediksi)
        ->get(route('admin.laporan.peramalan.index'))
        ->assertOk()
        ->assertSee('Laporan Peramalan')
        ->assertDontSee('Laporan Segmentasi');

    $this->actingAs($operatorPrediksi)
        ->get(route('admin.laporan.segmentasi.index'))
        ->assertForbidden();

    $this->actingAs($operatorPrediksi)
        ->get(
            route(
                'admin.laporan.kmeans.pdf',
                $dataset['kmeans']
            )
        )
        ->assertForbidden();
});
