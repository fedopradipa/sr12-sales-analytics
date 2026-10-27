<?php

namespace Tests\Feature\Services;

use App\Services\DesFastApiService;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use LogicException;
use UnexpectedValueException;

beforeEach(function (): void {
    config()->set([
        'services.analytics.url' => 'http://127.0.0.1:8010',
        'services.analytics.connect_timeout' => 3,
        'services.analytics.timeout' => 10,
    ]);

    Http::preventStrayRequests();
});

function fixedParameterBatchProducts(): array
{
    $series = [];

    for ($month = 1; $month <= 12; $month++) {
        $series[] = [
            'periode' => sprintf(
                '2024-%02d-01',
                $month
            ),
            'aktual' => 10 + $month,
        ];
    }

    return [
        [
            'produk_id' => 1,
            'alpha' => 0.25,
            'series' => $series,
        ],
        [
            'produk_id' => 2,
            'alpha' => 0.35,
            'series' => $series,
        ],
    ];
}

it('mengirim fixed alpha dalam satu request batch', function (): void {
    Http::fake([
        'http://127.0.0.1:8010/api/v1/des/forecast-batch' => function (Request $request) {
            expect($request->data())
                ->toHaveKey('products');

            expect(
                $request->data()['products'][0]
            )
                ->toHaveKey('alpha', 0.25)
                ->not->toHaveKey('mae_train')
                ->not->toHaveKey('wape_train');

            return Http::response([
                'results' => [
                    [
                        'produk_id' => 1,
                        'prediksi' => 25.5,
                    ],
                    [
                        'produk_id' => 2,
                        'prediksi' => 30.5,
                    ],
                ],
            ]);
        },
    ]);

    $results = app(
        DesFastApiService::class
    )->forecast(
        fixedParameterBatchProducts()
    );

    expect($results)->toBe([
        [
            'produk_id' => 1,
            'prediksi' => 25.5,
        ],
        [
            'produk_id' => 2,
            'prediksi' => 30.5,
        ],
    ]);

    Http::assertSentCount(1);
});

it('menolak field respons yang tidak termasuk kontrak', function (): void {
    Http::fake([
        'http://127.0.0.1:8010/api/v1/des/forecast-batch' => Http::response([
            'results' => [
                [
                    'produk_id' => 1,
                    'prediksi' => 25.5,
                    'alpha' => 0.25,
                ],
                [
                    'produk_id' => 2,
                    'prediksi' => 30.5,
                ],
            ],
        ]),
    ]);

    expect(
        fn () => app(
            DesFastApiService::class
        )->forecast(
            fixedParameterBatchProducts()
        )
    )->toThrow(
        UnexpectedValueException::class,
        'Respons layanan prediksi tidak sesuai kontrak.'
    );
});

it('menolak jumlah hasil yang tidak lengkap', function (): void {
    Http::fake([
        'http://127.0.0.1:8010/api/v1/des/forecast-batch' => Http::response([
            'results' => [
                [
                    'produk_id' => 1,
                    'prediksi' => 25.5,
                ],
            ],
        ]),
    ]);

    expect(
        fn () => app(
            DesFastApiService::class
        )->forecast(
            fixedParameterBatchProducts()
        )
    )->toThrow(UnexpectedValueException::class);
});

it('menolak daftar produk kosong tanpa mengirim request', function (): void {
    expect(
        fn () => app(
            DesFastApiService::class
        )->forecast([])
    )->toThrow(LogicException::class);

    Http::assertNothingSent();
});
