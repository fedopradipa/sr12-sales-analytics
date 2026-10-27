<?php

namespace Tests\Feature\Services;

use App\Models\DetailKmeans;
use App\Models\DetailTransaksi;
use App\Models\HasilKmeans;
use App\Models\ParameterDes;
use App\Models\Produk;
use App\Models\User;
use App\Services\DesForecastScopeService;
use App\Services\DesForecastService;
use App\Services\DesMonthlySalesService;
use App\Services\DesProcessCoverageService;
use App\Services\DesViewDataService;
use DomainException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Schema;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    File::deleteDirectory(
        storage_path(
            'framework/testing/des-forecast-scopes'
        )
    );

    config()->set([
        'services.analytics.url' => 'http://127.0.0.1:8010',
        'services.analytics.connect_timeout' => 3,
        'services.analytics.timeout' => 10,
    ]);

    Http::preventStrayRequests();
});

function fixedParameterDesDataset(
    bool $withParameters = true
): array {
    $user = User::query()->create([
        'name' => 'Admin DES',
        'email' => 'admin-des-service@example.test',
        'password' => 'password123',
        'role' => 'admin',
    ]);

    $products = collect([1, 2])->map(
        fn (int $number): Produk => Produk::query()->create([
            'kode' => 'DES'.str_pad(
                (string) $number,
                3,
                '0',
                STR_PAD_LEFT
            ),
            'nama' => 'Produk DES '.$number,
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
            'cluster' => 3,
        ]);
    }

    foreach ($products as $productIndex => $product) {
        for ($month = 1; $month <= 12; $month++) {
            $qty = 5 + $month + $productIndex;

            DetailTransaksi::query()->create([
                'user_id' => $user->id,
                'produk_id' => $product->id,
                'tanggal' => sprintf(
                    '2024-%02d-10',
                    $month
                ),
                'faktur' => sprintf(
                    'INV-DES-%d-%02d',
                    $product->id,
                    $month
                ),
                'qty' => $qty,
                'harga' => 10000,
                'subtotal' => $qty * 10000,
            ]);
        }
    }

    if ($withParameters) {
        foreach (
            $products->values() as $index => $product
        ) {
            ParameterDes::query()->create([
                'produk_id' => $product->id,
                'alpha' => $index === 0
                    ? 0.25
                    : 0.35,
            ]);
        }
    }

    return compact(
        'user',
        'products',
        'kmeans'
    );
}

function fixedParameterForecastResponse(
    array $products
): array {
    return [
        'results' => array_map(
            fn (array $product): array => [
                'produk_id' => $product['produk_id'],
                'prediksi' => 25.5 + $product['produk_id'],
            ],
            $products
        ),
    ];
}

it('menggunakan parameter alpha dan menyimpan prediksi satu bulan', function (): void {
    $dataset = fixedParameterDesDataset();

    Http::fake([
        'http://127.0.0.1:8010/api/v1/des/forecast-batch' => function (Request $request) {
            $products =
                $request->data()['products'];

            expect($products)->toHaveCount(2)
                ->and($products[0]['alpha'])
                ->toBe(0.25)
                ->and($products[1]['alpha'])
                ->toBe(0.35)
                ->and($products[0]['series'])
                ->toHaveCount(12)
                ->and(
                    $products[0]['series'][0]['periode']
                )->toBe('2024-01-01')
                ->and(
                    $products[0]['series'][11]['periode']
                )->toBe('2024-12-01');

            return Http::response(
                fixedParameterForecastResponse(
                    $products
                )
            );
        },
    ]);

    $process = app(
        DesForecastService::class
    )->createProcess(
        user: $dataset['user'],
        hasilKmeansId: $dataset['kmeans']->id,
        cluster: 3,
        produkId: null
    );

    expect(
        $process->data_awal
            ->format('Y-m-d')
    )->toBe('2024-01-01')
        ->and(
            $process->data_akhir
                ->format('Y-m-d')
        )->toBe('2024-12-01')
        ->and(
            $process->target
                ->format('Y-m-d')
        )->toBe('2025-01-01')
        ->and($process->hasilDes)
        ->toHaveCount(2)
        ->and(
            Schema::hasTable('parameter_des')
        )->toBeTrue()
        ->and(
            Schema::hasTable('model_des')
        )->toBeFalse()
        ->and(
            Schema::hasTable('detail_des')
        )->toBeFalse()
        ->and(
            Schema::hasColumn(
                'hasil_des',
                'produk_id'
            )
        )->toBeTrue();

    $this->assertDatabaseCount(
        'parameter_des',
        2
    );

    $this->assertDatabaseCount(
        'proses_des',
        1
    );

    $this->assertDatabaseCount(
        'hasil_des',
        2
    );

    $this->assertDatabaseHas(
        'hasil_des',
        [
            'proses_id' => $process->id,
            'produk_id' => $dataset['products'][0]->id,
            'target' => '2025-01-01',
        ]
    );
});

it('meramalkan hanya satu produk yang dipilih', function (): void {
    $dataset = fixedParameterDesDataset();
    $selectedProduct =
        $dataset['products'][1];

    Http::fake([
        'http://127.0.0.1:8010/api/v1/des/forecast-batch' => function (Request $request) use (
            $selectedProduct
        ) {
            $products =
                $request->data()['products'];

            expect($products)
                ->toHaveCount(1)
                ->and($products[0]['produk_id'])
                ->toBe($selectedProduct->id)
                ->and($products[0]['alpha'])
                ->toBe(0.35);

            return Http::response(
                fixedParameterForecastResponse(
                    $products
                )
            );
        },
    ]);

    $process = app(
        DesForecastService::class
    )->createProcess(
        user: $dataset['user'],
        hasilKmeansId: $dataset['kmeans']->id,
        cluster: 3,
        produkId: $selectedProduct->id
    );

    expect($process->hasilDes)
        ->toHaveCount(1)
        ->and(
            (int) $process->hasilDes
                ->first()
                ->produk_id
        )
        ->toBe($selectedProduct->id);

    $scope = app(
        DesForecastScopeService::class
    )->resolve($process);

    $coverage = app(
        DesProcessCoverageService::class
    )->summarize($process);

    expect($scope)
        ->not->toBeNull()
        ->and($scope['mode'])
        ->toBe('single')
        ->and($scope['produk_id'])
        ->toBe($selectedProduct->id)
        ->and($coverage['total'])
        ->toBe(1)
        ->and($coverage['forecasted'])
        ->toBe(1)
        ->and($coverage['skipped'])
        ->toBe(0)
        ->and($coverage['status'])
        ->toBe('complete');

    $this->assertDatabaseCount(
        'proses_des',
        1
    );

    $this->assertDatabaseCount(
        'hasil_des',
        1
    );

    $this->assertDatabaseHas(
        'hasil_des',
        [
            'proses_id' => $process->id,
            'produk_id' => $selectedProduct->id,
        ]
    );

    $this->assertDatabaseMissing(
        'hasil_des',
        [
            'proses_id' => $process->id,
            'produk_id' => $dataset['products'][0]->id,
        ]
    );
});

it(
    'menolak peramalan satu produk bila parameter DES belum tersedia',
    function (): void {
        $dataset = fixedParameterDesDataset();
        $selectedProduct =
            $dataset['products'][1];

        ParameterDes::query()
            ->where(
                'produk_id',
                $selectedProduct->id
            )
            ->delete();

        expect(
            fn () => app(
                DesForecastService::class
            )->createProcess(
                user: $dataset['user'],
                hasilKmeansId: $dataset['kmeans']->id,
                cluster: 3,
                produkId: $selectedProduct->id
            )
        )->toThrow(
            DomainException::class,
            'Produk yang dipilih belum memiliki parameter DES'
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

it('menggunakan alpha yang sama setelah data bulan baru ditambahkan', function (): void {
    $dataset = fixedParameterDesDataset();

    Http::fake([
        'http://127.0.0.1:8010/api/v1/des/forecast-batch' => fn (Request $request) => Http::response(
            fixedParameterForecastResponse(
                $request->data()['products']
            )
        ),
    ]);

    app(DesForecastService::class)->createProcess(
        user: $dataset['user'],
        hasilKmeansId: $dataset['kmeans']->id,
        cluster: 3,
        produkId: null
    );

    foreach ($dataset['products'] as $product) {
        DetailTransaksi::query()->create([
            'user_id' => $dataset['user']->id,
            'produk_id' => $product->id,
            'tanggal' => '2025-01-10',
            'faktur' => 'INV-JAN-'.$product->id,
            'qty' => 20,
            'harga' => 10000,
            'subtotal' => 200000,
        ]);
    }

    Http::fake([
        'http://127.0.0.1:8010/api/v1/des/forecast-batch' => function (Request $request) {
            $products =
                $request->data()['products'];

            expect($products[0]['alpha'])
                ->toBe(0.25)
                ->and($products[1]['alpha'])
                ->toBe(0.35)
                ->and($products[0]['series'])
                ->toHaveCount(13);

            return Http::response(
                fixedParameterForecastResponse(
                    $products
                )
            );
        },
    ]);

    $secondProcess = app(
        DesForecastService::class
    )->createProcess(
        user: $dataset['user'],
        hasilKmeansId: $dataset['kmeans']->id,
        cluster: 3,
        produkId: null
    );

    expect(
        $secondProcess->target
            ->format('Y-m-d')
    )->toBe('2025-02-01');

    $this->assertDatabaseCount(
        'parameter_des',
        2
    );

    $this->assertDatabaseCount(
        'proses_des',
        2
    );

    $this->assertDatabaseCount(
        'hasil_des',
        4
    );
});

it('menolak produk yang belum memiliki parameter sebelum memanggil FastAPI', function (): void {
    $dataset = fixedParameterDesDataset(
        withParameters: false
    );

    expect(
        fn () => app(
            DesForecastService::class
        )->createProcess(
            user: $dataset['user'],
            hasilKmeansId: $dataset['kmeans']->id,
            cluster: 3,
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
});

it('mengizinkan peramalan ulang untuk target yang sama setelah transaksi diedit', function (): void {
    $dataset = fixedParameterDesDataset();

    Http::fake([
        'http://127.0.0.1:8010/api/v1/des/forecast-batch' => fn (Request $request) => Http::response(
            fixedParameterForecastResponse(
                $request->data()['products']
            )
        ),
    ]);

    $service = app(
        DesForecastService::class
    );

    $firstProcess = $service->createProcess(
        user: $dataset['user'],
        hasilKmeansId: $dataset['kmeans']->id,
        cluster: 3,
        produkId: null
    );

    $transaction = DetailTransaksi::query()
        ->where(
            'produk_id',
            $dataset['products']->first()->id
        )
        ->orderBy('tanggal')
        ->firstOrFail();

    $transaction->update([
        'qty' => 99,
        'subtotal' => 990000,
    ]);

    $secondProcess = $service->createProcess(
        user: $dataset['user'],
        hasilKmeansId: $dataset['kmeans']->id,
        cluster: 3,
        produkId: null
    );

    expect(
        $firstProcess->target->format('Y-m-d')
    )->toBe(
        $secondProcess->target->format('Y-m-d')
    )->and(
        $firstProcess->id
    )->not->toBe($secondProcess->id);

    $this->assertDatabaseCount(
        'proses_des',
        2
    );

    $this->assertDatabaseCount(
        'hasil_des',
        4
    );

    Http::assertSentCount(2);
});

it('tidak menyimpan proses ketika layanan prediksi gagal', function (): void {
    $dataset = fixedParameterDesDataset();

    Http::fake([
        'http://127.0.0.1:8010/api/v1/des/forecast-batch' => Http::response(
            ['detail' => 'gagal'],
            500
        ),
    ]);

    expect(
        fn () => app(
            DesForecastService::class
        )->createProcess(
            user: $dataset['user'],
            hasilKmeansId: $dataset['kmeans']->id,
            cluster: 3,
            produkId: null
        )
    )->toThrow(RequestException::class);

    $this->assertDatabaseCount(
        'parameter_des',
        2
    );

    $this->assertDatabaseCount(
        'proses_des',
        0
    );

    $this->assertDatabaseCount(
        'hasil_des',
        0
    );
});

it(
    'menyiapkan histori bulanan batch untuk workspace prediksi',
    function (): void {
        $dataset = fixedParameterDesDataset();

        Http::fake([
            'http://127.0.0.1:8010/api/v1/des/forecast-batch' => function (Request $request) {
                return Http::response(
                    fixedParameterForecastResponse(
                        $request->data()['products']
                    )
                );
            },
        ]);

        $process = app(
            DesForecastService::class
        )->createProcess(
            user: $dataset['user'],
            hasilKmeansId: $dataset['kmeans']->id,
            cluster: 3,
            produkId: null
        );

        $results = app(
            DesViewDataService::class
        )->processResults($process);

        $series = app(
            DesMonthlySalesService::class
        )->historicalSeriesBatch(
            $process,
            $results
        );

        expect($results)
            ->toHaveCount(2);

        foreach (
            $dataset['products'] as $product
        ) {
            expect($series)
                ->toHaveKey($product->id);

            expect(
                $series[$product->id]
            )->toHaveCount(12);
        }

        $firstProductId =
            $dataset['products']
                ->first()
                ->id;

        expect(
            $series[$firstProductId][0][
                'periode'
            ]->format('Y-m-d')
        )->toBe('2024-01-01');

        expect(
            $series[$firstProductId][0][
                'aktual'
            ]
        )->toBe(6);

        Http::assertSentCount(1);
    }
);
