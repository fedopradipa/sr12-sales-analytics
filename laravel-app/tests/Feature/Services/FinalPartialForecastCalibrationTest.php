<?php

namespace Tests\Feature\Services;

use App\Models\DetailKmeans;
use App\Models\DetailTransaksi;
use App\Models\HasilKmeans;
use App\Models\ParameterDes;
use App\Models\Produk;
use App\Models\User;
use App\Services\DesCalibrationResultService;
use App\Services\DesCalibrationService;
use App\Services\DesForecastService;
use App\Services\DesProcessCoverageService;
use App\Services\DesStockService;
use DomainException;
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

    config()->set([
        'services.analytics.url' => 'http://127.0.0.1:8010',
        'services.analytics.connect_timeout' => 3,
        'services.analytics.timeout' => 10,
    ]);

    Http::preventStrayRequests();
});

function partialForecastDataset(
    int $productCount = 3
): array {
    $user = User::query()->create([
        'name' => 'Admin Partial Forecast',
        'email' => 'partial-forecast@example.test',
        'password' => 'password123',
        'role' => 'admin',
    ]);

    $products = collect(
        range(1, $productCount)
    )->map(
        fn (int $number): Produk => Produk::query()->create([
            'kode' => 'PFX'.str_pad(
                (string) $number,
                3,
                '0',
                STR_PAD_LEFT
            ),
            'nama' => 'Produk Partial '.$number,
        ])
    );

    $kmeans = HasilKmeans::query()->create([
        'user_id' => $user->id,
        'awal' => '2024-01-01',
        'akhir' => '2024-12-31',
        'iterasi' => 3,
    ]);

    foreach ($products as $product) {
        DetailKmeans::query()->create([
            'kmeans_id' => $kmeans->id,
            'produk_id' => $product->id,
            'recency' => 0,
            'frekuensi' => 12,
            'qty' => 120,
            'omzet' => 1200000,
            'cluster' => 1,
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
                    'INV-PFX-%d-%02d',
                    $product->id,
                    $month
                ),
                'qty' => 10 + $month,
                'harga' => 10000,
                'subtotal' => (10 + $month) * 10000,
            ]);
        }
    }

    return compact(
        'user',
        'products',
        'kmeans'
    );
}

it(
    'meramalkan produk yang memiliki parameter dan melewati produk tanpa parameter',
    function (): void {
        $dataset = partialForecastDataset();

        foreach (
            $dataset['products']->take(2) as $index => $product
        ) {
            ParameterDes::query()->create([
                'produk_id' => $product->id,
                'alpha' => $index === 0
                    ? 0.25
                    : 0.35,
            ]);
        }

        Http::fake([
            'http://127.0.0.1:8010/api/v1/des/forecast-batch' => function (Request $request) {
                $products =
                    $request->data()['products'];

                expect($products)
                    ->toHaveCount(2);

                return Http::response([
                    'results' => array_map(
                        fn (array $product): array => [
                            'produk_id' => $product['produk_id'],
                            'prediksi' => 25.5,
                        ],
                        $products
                    ),
                ]);
            },
        ]);

        $process = app(
            DesForecastService::class
        )->createProcess(
            user: $dataset['user'],
            hasilKmeansId: $dataset['kmeans']->id,
            cluster: 1,
            produkId: null
        );

        $coverage = app(
            DesProcessCoverageService::class
        )->summarize($process);

        expect($process->hasilDes)
            ->toHaveCount(2)
            ->and($coverage['total'])
            ->toBe(3)
            ->and($coverage['forecasted'])
            ->toBe(2)
            ->and($coverage['skipped'])
            ->toBe(1)
            ->and($coverage['status_label'])
            ->toBe('Selesai Sebagian')
            ->and(
                $coverage[
                    'skipped_products'
                ][0]['kode']
            )
            ->toBe('PFX003');

        $this->actingAs($dataset['user'])
            ->get(
                route(
                    'admin.des.process.show',
                    $process
                )
            )
            ->assertOk()
            ->assertSeeText('Selesai Sebagian')
            ->assertSeeText('Produk Tidak Diramalkan')
            ->assertSeeText('PFX003')
            ->assertSeeText(
                'Parameter DES belum tersedia pada saat peramalan.'
            );

        $this->actingAs($dataset['user'])
            ->get(
                route(
                    'admin.laporan.peramalan.index'
                )
            )
            ->assertOk()
            ->assertSeeText('Selesai Sebagian')
            ->assertSeeText('2')
            ->assertSeeText('3');

        $process->loadMissing([
            'user',
            'hasilKmeans.detail.produk',
            'hasilDes.produk',
        ]);

        $rows = $process->hasilDes
            ->map(
                fn ($hasil): array => [
                    'hasil' => $hasil,
                    'inventory' => app(
                        DesStockService::class
                    )->evaluate($hasil),
                ]
            );

        $html = view(
            'admin.laporan.pdf.des-batch',
            [
                'prosesDes' => $process,
                'rows' => $rows,
                'coverage' => $coverage,
                'logoData' => null,
            ]
        )->render();

        expect($html)
            ->toContain('Selesai Sebagian')
            ->toContain('PRODUK TIDAK DIRAMALKAN')
            ->toContain('PFX003')
            ->toContain(
                'Parameter DES belum tersedia pada saat peramalan.'
            );
    }
);

it(
    'menolak peramalan bila seluruh produk tidak memiliki parameter',
    function (): void {
        $dataset = partialForecastDataset();

        expect(
            fn () => app(
                DesForecastService::class
            )->createProcess(
                user: $dataset['user'],
                hasilKmeansId: $dataset['kmeans']->id,
                cluster: 1,
                produkId: null
            )
        )->toThrow(
            DomainException::class,
            'tidak ada produk pada kelompok ini yang memiliki parameter DES'
        );

        Http::assertNothingSent();

        $this->assertDatabaseCount(
            'proses_des',
            0
        );

        $this->assertDatabaseCount(
            'hasil_des',
            0
        );
    }
);

it(
    'menghapus parameter lama produk skipped ketika kalibrasi sebagian berhasil',
    function (): void {
        $dataset = partialForecastDataset(
            productCount: 2
        );

        $validProduct =
            $dataset['products'][0];

        $skippedProduct =
            $dataset['products'][1];

        DetailTransaksi::query()
            ->where(
                'produk_id',
                $skippedProduct->id
            )
            ->delete();

        foreach (
            $dataset['products'] as $product
        ) {
            ParameterDes::query()->create([
                'produk_id' => $product->id,
                'alpha' => 0.50,
            ]);
        }

        Http::fake([
            'http://127.0.0.1:8010/api/v1/des/calibrate-batch' => function (Request $request) use (
                $validProduct
            ) {
                $products =
                    $request->data()['products'];

                expect($products)
                    ->toHaveCount(1)
                    ->and(
                        $products[0]['produk_id']
                    )
                    ->toBe($validProduct->id);

                return Http::response([
                    'results' => [[
                        'produk_id' => $validProduct->id,
                        'alpha' => 0.22,
                        'mae_train' => 1.1,
                        'wape_train' => 5.5,
                        'mae_test' => 1.3,
                        'wape_test' => 6.0,
                    ]],
                ]);
            },
        ]);

        $summary = app(
            DesCalibrationService::class
        )->calibrate(1);

        expect($summary['updated'])
            ->toBe(1)
            ->and($summary['skipped'])
            ->toBe(1)
            ->and($summary['skipped_products'])
            ->toHaveCount(1)
            ->and($summary['skipped_products'][0])
            ->toMatchArray([
                'produk_id' => $skippedProduct->id,
                'kode' => (string) $skippedProduct->kode,
                'nama' => (string) $skippedProduct->nama,
                'reason_code' => 'no_transactions',
                'reason' => 'Belum memiliki data transaksi penjualan.',
            ]);

        $storedSummary = app(
            DesCalibrationResultService::class
        )->latest();

        expect($storedSummary)
            ->not->toBeNull()
            ->and($storedSummary['skipped'])
            ->toBe(1)
            ->and(
                $storedSummary[
                    'skipped_products'
                ][0]['reason_code']
            )
            ->toBe('no_transactions');

        $this->assertDatabaseHas(
            'parameter_des',
            [
                'produk_id' => $validProduct->id,
                'alpha' => 0.22,
            ]
        );

        $this->assertDatabaseMissing(
            'parameter_des',
            [
                'produk_id' => $skippedProduct->id,
            ]
        );
    }
);

it(
    'menghapus parameter lama ketika seluruh produk kalibrasi skipped',
    function (): void {
        $user = User::query()->create([
            'name' => 'Admin All Skipped',
            'email' => 'all-skipped@example.test',
            'password' => 'password123',
            'role' => 'admin',
        ]);

        $products = collect([1, 2])->map(
            fn (int $number): Produk => Produk::query()->create([
                'kode' => 'ASK'.$number,
                'nama' => 'All Skipped '.$number,
            ])
        );

        foreach ($products as $product) {
            ParameterDes::query()->create([
                'produk_id' => $product->id,
                'alpha' => 0.40,
            ]);
        }

        DetailTransaksi::query()->create([
            'user_id' => $user->id,
            'produk_id' => $products[0]->id,
            'tanggal' => '2024-01-10',
            'faktur' => 'INV-ASK-01',
            'qty' => 10,
            'harga' => 10000,
            'subtotal' => 100000,
        ]);

        expect(
            fn () => app(
                DesCalibrationService::class
            )->calibrate(1)
        )->toThrow(
            DomainException::class,
            'Tidak ada produk yang memiliki data train/test cukup'
        );

        Http::assertNothingSent();

        $this->assertDatabaseCount(
            'parameter_des',
            0
        );

        foreach ($products as $product) {
            $this->assertDatabaseMissing(
                'parameter_des',
                [
                    'produk_id' => $product->id,
                ]
            );
        }

        $storedSummary = app(
            DesCalibrationResultService::class
        )->latest();

        expect($storedSummary)
            ->not->toBeNull()
            ->and($storedSummary['updated'])
            ->toBe(0)
            ->and($storedSummary['skipped'])
            ->toBe(2)
            ->and(
                collect(
                    $storedSummary[
                        'skipped_products'
                    ]
                )->pluck('reason_code')->all()
            )
            ->toBe([
                'insufficient_periods',
                'no_transactions',
            ]);
    }
);
