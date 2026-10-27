<?php

use App\Models\DetailKmeans;
use App\Models\DetailTransaksi;
use App\Models\HasilKmeans;
use App\Models\ParameterDes;
use App\Models\Permission;
use App\Models\Produk;
use App\Models\ProsesDes;
use App\Models\User;
use App\Services\DesCalibrationResultService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Http;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    File::deleteDirectory(
        storage_path(
            'framework/testing/des-forecast-scopes'
        )
    );

    File::deleteDirectory(
        storage_path(
            'framework/testing/des-calibration-results'
        )
    );

    config()->set(
        'services.analytics.url',
        'http://127.0.0.1:8010'
    );

    Http::preventStrayRequests();
});

function fixedParameterControllerDataset(): array
{
    $user = User::query()->create([
        'name' => 'Admin DES Controller',
        'email' => 'admin-des-controller@example.test',
        'password' => 'password123',
        'role' => 'admin',
    ]);

    $product = Produk::query()->create([
        'kode' => 'DESC001',
        'nama' => 'Produk Controller',
        'stok' => 15,
        'stok_min' => 20,
        'stok_at' => '2026-08-09 10:00:00',
    ]);

    ParameterDes::query()->create([
        'produk_id' => $product->id,
        'alpha' => 0.25,
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
        $qty = 5 + $month;

        DetailTransaksi::query()->create([
            'user_id' => $user->id,
            'produk_id' => $product->id,
            'tanggal' => sprintf(
                '2024-%02d-10',
                $month
            ),
            'faktur' => sprintf(
                'INV-C-%02d',
                $month
            ),
            'qty' => $qty,
            'harga' => 10000,
            'subtotal' => $qty * 10000,
        ]);
    }

    return compact(
        'user',
        'product',
        'kmeans'
    );
}

it('mengarahkan guest dan menolak operator tanpa permission des', function (): void {
    $this->get(route('admin.des.index'))
        ->assertRedirect(route('login'));

    $operator = User::query()->create([
        'name' => 'Operator Tanpa DES',
        'email' => 'operator-tanpa-des@example.test',
        'password' => 'password123',
        'role' => 'operator',
    ]);

    $this->actingAs($operator)
        ->get(route('admin.des.index'))
        ->assertForbidden();
});

it('memproses fixed alpha dan menampilkan satu tabel hasil', function (): void {
    $dataset =
        fixedParameterControllerDataset();

    Http::fake([
        'http://127.0.0.1:8010/api/v1/des/forecast-batch' => function (Request $request) {
            $product =
                $request->data()['products'][0];

            expect($product['alpha'])
                ->toBe(0.25)
                ->and($product)
                ->not->toHaveKey('mae_train')
                ->not->toHaveKey('wape_train');

            return Http::response([
                'results' => [[
                    'produk_id' => $product['produk_id'],
                    'prediksi' => 21.0,
                ]],
            ]);
        },
    ]);

    $response = $this
        ->actingAs($dataset['user'])
        ->post(route('admin.des.store'), [
            'kmeans_id' => $dataset['kmeans']->id,
            'cluster' => 2,
            'produk_id' => '',
        ]);

    $process = ProsesDes::query()
        ->firstOrFail();

    $response->assertRedirect(
        route(
            'admin.des.process.show',
            $process
        )
    );

    $this->actingAs($dataset['user'])
        ->get(
            route(
                'admin.des.process.show',
                $process
            )
        )
        ->assertOk()
        ->assertSee('Produk Controller')
        ->assertSee('Januari 2025')
        ->assertSee('Kelompok Produk')
        ->assertSee('xl:grid-cols-6', false)
        ->assertSee('Pergerakan Sedang')
        ->assertSee('Periode Segmentasi')
        ->assertSee('Januari 2024')
        ->assertSee('Desember 2024')
        ->assertSee('Target Peramalan')
        ->assertSee('Lengkap')
        ->assertSee('Berhasil')
        ->assertSee('1 dari')
        ->assertSee('Tidak Diramalkan')
        ->assertSee('0 Produk')
        ->assertSee('21,00')
        ->assertSee('Kode')
        ->assertSee('Nama Produk')
        ->assertSee('Prediksi')
        ->assertSee('Stok Saat Peramalan')
        ->assertSee('Stok Minimum')
        ->assertSee('Stok Perlu Ditambah')
        ->assertSee('Status')
        ->assertSee('Pcs')
        ->assertSee('Kembali')
        ->assertSee(
            'border-red-400',
            false
        )
        ->assertSee('Kritis')
        ->assertDontSee(
            'des-workspace-chart',
            false
        )
        ->assertDontSee(
            'des-workspace-data',
            false
        )
        ->assertDontSee(
            'admin.des.show',
            false
        );
});

it('meneruskan produk_id untuk peramalan satu produk', function (): void {
    $dataset =
        fixedParameterControllerDataset();

    $otherProduct = Produk::query()->create([
        'kode' => 'DESC002',
        'nama' => 'Produk Controller Lain',
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

    Http::fake([
        'http://127.0.0.1:8010/api/v1/des/forecast-batch' => function (Request $request) use ($dataset) {
            $products =
                $request->data()['products'];

            expect($products)
                ->toHaveCount(1)
                ->and($products[0]['produk_id'])
                ->toBe($dataset['product']->id);

            return Http::response([
                'results' => [[
                    'produk_id' => $dataset['product']->id,
                    'prediksi' => 21.0,
                ]],
            ]);
        },
    ]);

    $response = $this
        ->actingAs($dataset['user'])
        ->post(route('admin.des.store'), [
            'kmeans_id' => $dataset['kmeans']->id,
            'cluster' => 2,
            'produk_id' => $dataset['product']->id,
        ]);

    $process = ProsesDes::query()
        ->firstOrFail();

    $response->assertRedirect(
        route(
            'admin.des.process.show',
            [
                'prosesDes' => $process,
                'produk_id' => $dataset['product']->id,
            ]
        )
    );

    $this->actingAs($dataset['user'])
        ->get(
            route(
                'admin.des.process.show',
                [
                    'prosesDes' => $process,
                    'produk_id' => $dataset['product']->id,
                ]
            )
        )
        ->assertOk()
        ->assertViewHas(
            'coverage',
            fn (array $coverage): bool => $coverage['total'] === 1
                && $coverage['forecasted'] === 1
                && $coverage['skipped'] === 0
                && $coverage['status'] === 'complete'
        )
        ->assertSee('Lengkap')
        ->assertSeeText('Berhasil')
        ->assertSeeText('Tidak Diramalkan')
        ->assertSee('0 Produk');

    /*
     * Scope satu produk harus tetap benar tanpa query string,
     * termasuk setelah sesi login berganti.
     */
    $this->post(route('logout'));

    $this->actingAs($dataset['user'])
        ->get(
            route(
                'admin.des.process.show',
                $process
            )
        )
        ->assertOk()
        ->assertViewHas(
            'coverage',
            fn (array $coverage): bool => $coverage['total'] === 1
                && $coverage['forecasted'] === 1
                && $coverage['skipped'] === 0
                && $coverage['status'] === 'complete'
        )
        ->assertDontSee('Produk Controller Lain');

    $this->actingAs($dataset['user'])
        ->get(route('admin.des.index'))
        ->assertOk()
        ->assertViewHas(
            'daftarProses',
            function ($paginator) use ($process): bool {
                $row = $paginator
                    ->getCollection()
                    ->firstWhere(
                        'id',
                        $process->id
                    );

                return $row instanceof ProsesDes
                    && $row->coverage['total'] === 1
                    && $row->coverage['forecasted'] === 1
                    && $row->coverage['skipped'] === 0
                    && $row->coverage['status'] === 'complete';
            }
        );

    $this->assertDatabaseCount(
        'hasil_des',
        1
    );
});

it('form modal hanya mendukung prediksi satu bulan berikutnya', function (): void {
    $dataset =
        fixedParameterControllerDataset();

    $this->actingAs($dataset['user'])
        ->get(route('admin.des.index'))
        ->assertOk()
        ->assertSee(
            'data-des-modal-open="des-form-modal"',
            false
        )
        ->assertSee(
            'id="des-form-modal"',
            false
        )
        ->assertSee(
            'selalu meramalkan satu bulan berikutnya'
        )
        ->assertSee('Januari 2025')
        ->assertDontSee(
            'Jumlah Bulan Prediksi'
        )
        ->assertDontSee(
            'name="jml_prediksi"',
            false
        )
        ->assertSee(
            'name="produk_id"',
            false
        )
        ->assertSee('Target Produk')
        ->assertSee('Seluruh Produk');

    $this->actingAs($dataset['user'])
        ->get(route('admin.des.create'))
        ->assertRedirect(
            route(
                'admin.des.index',
                ['open_modal' => 1]
            )
        );
});

it('operator dengan permission des dapat membuka modul', function (): void {
    $operator = User::query()->create([
        'name' => 'Operator DES',
        'email' => 'operator-des@example.test',
        'password' => 'password123',
        'role' => 'operator',
    ]);

    $permission = Permission::query()->create([
        'code' => 'des',
        'name' => 'DES',
    ]);

    $operator->permissions()->sync([
        $permission->id,
    ]);

    $this->actingAs($operator)
        ->get(route('admin.des.index'))
        ->assertOk()
        ->assertSee('Dashboard');
});

it(
    'halaman penentuan parameter menampilkan produk dilewati secara persisten',
    function (): void {
        $dataset = fixedParameterControllerDataset();

        app(
            DesCalibrationResultService::class
        )->remember([
            'updated' => 1,
            'skipped' => 1,
            'test_months' => 8,
            'average_wape' => 6.25,
            'skipped_products' => [[
                'produk_id' => 999,
                'kode' => 'SKIP001',
                'nama' => 'Produk Tanpa Transaksi',
                'reason_code' => 'no_transactions',
                'reason' => 'Belum memiliki data transaksi penjualan.',
            ]],
        ]);

        foreach ([1, 2] as $visit) {
            $this->actingAs($dataset['user'])
                ->get(
                    route(
                        'admin.kalibrasi-parameter.index'
                    )
                )
                ->assertOk()
                ->assertSeeText(
                    'Penentuan Parameter DES'
                )
                ->assertSeeText(
                    'Parameter Aktif & Evaluasi'
                )
                ->assertSeeText(
                    'WAPE Train terendah'
                )
                ->assertDontSeeText(
                    'WAPE Test terendah'
                )
                ->assertSeeText('Produk Dilewati')
                ->assertSeeText('SKIP001')
                ->assertSeeText(
                    'Produk Tanpa Transaksi'
                )
                ->assertSeeText(
                    'Belum memiliki data transaksi penjualan.'
                )
                ->assertSee(
                    'data-des-calibration-open',
                    false
                )
                ->assertSee(
                    'shadow-[0_35px_100px_rgba(77,36,43,.34)]',
                    false
                )
                ->assertSee(
                    'bg-gradient-to-r from-rose-50 to-white',
                    false
                );
        }
    }
);

it(
    'hasil peramalan final hanya satu tabel tanpa detail dan alpha',
    function (): void {
        $routes = file_get_contents(
            base_path('routes/web.php')
        );

        $controller = file_get_contents(
            app_path('Http/Controllers/Admin/DesController.php')
        );

        $view = file_get_contents(
            resource_path(
                'views/admin/des/process.blade.php'
            )
        );

        $appJs = file_get_contents(
            resource_path('js/app.js')
        );

        expect($routes)
            ->not->toContain('/hasil/{hasilDes}');

        expect($controller)
            ->not->toContain('historicalSeriesBatch(')
            ->not->toContain('public function show(')
            ->not->toContain("'series' =>");

        expect($view)
            ->not->toContain('des-workspace-chart')
            ->not->toContain('des-workspace-data')
            ->not->toContain('des-detail-panel')
            ->not->toContain('des-split-workspace')
            ->not->toContain('admin.des.show')
            ->not->toContain('>Detail<')
            ->not->toContain('>Aksi<')
            ->not->toContain('>Alpha<')
            ->toContain('Kode')
            ->toContain('Nama Produk')
            ->toContain('Prediksi')
            ->toContain('Stok Saat Peramalan')
            ->toContain('Stok Minimum')
            ->toContain('Stok Perlu Ditambah')
            ->toContain('Status');

        expect($appJs)
            ->not->toContain('des-detail-chart.js')
            ->not->toContain(
                'des-process-workspace.js'
            );

        expect(
            file_exists(
                resource_path(
                    'views/admin/des/show.blade.php'
                )
            )
        )->toBeFalse();

        expect(
            file_exists(
                resource_path(
                    '../js/modules/des-detail-chart.js'
                )
            )
        )->toBeFalse();

        expect(
            file_exists(
                resource_path(
                    '../js/modules/des-process-workspace.js'
                )
            )
        )->toBeFalse();
    }
);
