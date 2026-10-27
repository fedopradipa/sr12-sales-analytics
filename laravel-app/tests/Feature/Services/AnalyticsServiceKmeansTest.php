<?php

namespace Tests\Feature\Services;

use App\Services\AnalyticsService;
use Illuminate\Http\Client\Request;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Http;
use InvalidArgumentException;
use Tests\TestCase;
use UnexpectedValueException;

class AnalyticsServiceKmeansTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        config()->set([
            'services.analytics.url' => 'http://127.0.0.1:8010',
            'services.analytics.connect_timeout' => 3,
            'services.analytics.timeout' => 10,
        ]);

        Http::preventStrayRequests();
    }

    public function test_analyze_kmeans_mengirim_payload_dan_menerima_hasil(): void
    {
        $products = $this->products();

        $expectedResponse = [
            'k' => 3,
            'iterasi' => 2,
            'results' => [
                [
                    'produk_id' => 5,
                    'recency' => 1,
                    'frekuensi' => 30,
                    'qty' => 600,
                    'omzet' => 30000000,
                    'cluster' => 1,
                ],
                [
                    'produk_id' => 6,
                    'recency' => 0,
                    'frekuensi' => 31,
                    'qty' => 650,
                    'omzet' => 32000000,
                    'cluster' => 1,
                ],
                [
                    'produk_id' => 3,
                    'recency' => 15,
                    'frekuensi' => 12,
                    'qty' => 150,
                    'omzet' => 7500000,
                    'cluster' => 2,
                ],
                [
                    'produk_id' => 4,
                    'recency' => 14,
                    'frekuensi' => 13,
                    'qty' => 170,
                    'omzet' => 8000000,
                    'cluster' => 2,
                ],
                [
                    'produk_id' => 1,
                    'recency' => 30,
                    'frekuensi' => 1,
                    'qty' => 10,
                    'omzet' => 100000,
                    'cluster' => 3,
                ],
                [
                    'produk_id' => 2,
                    'recency' => 29,
                    'frekuensi' => 2,
                    'qty' => 15,
                    'omzet' => 150000,
                    'cluster' => 3,
                ],
            ],
        ];

        Http::fake([
            'http://127.0.0.1:8010/api/kmeans/analyze' => Http::response($expectedResponse),
        ]);

        $result = app(AnalyticsService::class)
            ->analyzeKmeans($products);

        $this->assertSame(
            $expectedResponse,
            $result
        );

        Http::assertSent(
            fn (Request $request): bool => $request->method() === 'POST'
                && $request->url()
                    === 'http://127.0.0.1:8010/api/kmeans/analyze'
                && $request->data() === [
                    'products' => $products,
                ]
        );

        Http::assertSentCount(1);
    }

    public function test_analyze_kmeans_menolak_produk_kurang_dari_tiga(): void
    {
        try {
            app(AnalyticsService::class)
                ->analyzeKmeans(
                    array_slice(
                        $this->products(),
                        0,
                        2
                    )
                );

            $this->fail(
                'InvalidArgumentException tidak dilempar.'
            );
        } catch (InvalidArgumentException $exception) {
            $this->assertSame(
                'Data segmentasi minimal terdiri dari 3 produk.',
                $exception->getMessage()
            );
        }

        Http::assertNothingSent();
    }

    public function test_analyze_kmeans_menolak_produk_duplikat(): void
    {
        $products = $this->products();
        $products[1]['produk_id'] = 1;

        try {
            app(AnalyticsService::class)
                ->analyzeKmeans($products);

            $this->fail(
                'InvalidArgumentException tidak dilempar.'
            );
        } catch (InvalidArgumentException $exception) {
            $this->assertSame(
                'produk_id 1 terdapat lebih dari satu kali.',
                $exception->getMessage()
            );
        }

        Http::assertNothingSent();
    }

    public function test_analyze_kmeans_menolak_atribut_negatif(): void
    {
        $products = $this->products();
        $products[0]['qty'] = -1;

        try {
            app(AnalyticsService::class)
                ->analyzeKmeans($products);

            $this->fail(
                'InvalidArgumentException tidak dilempar.'
            );
        } catch (InvalidArgumentException $exception) {
            $this->assertStringContainsString(
                'tidak boleh negatif',
                $exception->getMessage()
            );
        }

        Http::assertNothingSent();
    }

    public function test_analyze_kmeans_menolak_k_yang_bukan_tiga(): void
    {
        Http::fake([
            'http://127.0.0.1:8010/api/kmeans/analyze' => Http::response([
                'k' => 4,
                'iterasi' => 2,
                'results' => [],
            ]),
        ]);

        $this->expectException(
            UnexpectedValueException::class
        );

        $this->expectExceptionMessage(
            'Respons layanan segmentasi tidak sesuai kontrak.'
        );

        app(AnalyticsService::class)
            ->analyzeKmeans($this->products());
    }

    public function test_analyze_kmeans_menolak_produk_hasil_yang_berbeda(): void
    {
        $response = $this->validResponse();

        $response['results'][0]['produk_id'] = 999;

        Http::fake([
            'http://127.0.0.1:8010/api/kmeans/analyze' => Http::response($response),
        ]);

        $this->expectException(
            UnexpectedValueException::class
        );

        app(AnalyticsService::class)
            ->analyzeKmeans($this->products());
    }

    public function test_analyze_kmeans_meneruskan_error_http_service(): void
    {
        Http::fake([
            'http://127.0.0.1:8010/api/kmeans/analyze' => Http::response([
                'detail' => 'Data segmentasi tidak valid.',
            ], 422),
        ]);

        $this->expectException(
            RequestException::class
        );

        app(AnalyticsService::class)
            ->analyzeKmeans($this->products());
    }

    /**
     * @return list<array{
     *     produk_id: int,
     *     frekuensi: int,
     *     qty: int,
     *     omzet: int
     * }>
     */
    private function products(): array
    {
        return [
            [
                'produk_id' => 1,
                'recency' => 30,
                'frekuensi' => 1,
                'qty' => 10,
                'omzet' => 100000,
            ],
            [
                'produk_id' => 2,
                'recency' => 29,
                'frekuensi' => 2,
                'qty' => 15,
                'omzet' => 150000,
            ],
            [
                'produk_id' => 3,
                'recency' => 15,
                'frekuensi' => 12,
                'qty' => 150,
                'omzet' => 7500000,
            ],
            [
                'produk_id' => 4,
                'recency' => 14,
                'frekuensi' => 13,
                'qty' => 170,
                'omzet' => 8000000,
            ],
            [
                'produk_id' => 5,
                'recency' => 1,
                'frekuensi' => 30,
                'qty' => 600,
                'omzet' => 30000000,
            ],
            [
                'produk_id' => 6,
                'recency' => 0,
                'frekuensi' => 31,
                'qty' => 650,
                'omzet' => 32000000,
            ],
        ];
    }

    /**
     * @return array{
     *     k: int,
     *     iterasi: int,
     *     results: list<array{
     *         produk_id: int,
     *         frekuensi: int,
     *         qty: int,
     *         omzet: int,
     *         cluster: int
     *     }>
     * }
     */
    private function validResponse(): array
    {
        return [
            'k' => 3,
            'iterasi' => 2,
            'results' => [
                [
                    'produk_id' => 5,
                    'recency' => 1,
                    'frekuensi' => 30,
                    'qty' => 600,
                    'omzet' => 30000000,
                    'cluster' => 1,
                ],
                [
                    'produk_id' => 6,
                    'recency' => 0,
                    'frekuensi' => 31,
                    'qty' => 650,
                    'omzet' => 32000000,
                    'cluster' => 1,
                ],
                [
                    'produk_id' => 3,
                    'recency' => 15,
                    'frekuensi' => 12,
                    'qty' => 150,
                    'omzet' => 7500000,
                    'cluster' => 2,
                ],
                [
                    'produk_id' => 4,
                    'recency' => 14,
                    'frekuensi' => 13,
                    'qty' => 170,
                    'omzet' => 8000000,
                    'cluster' => 2,
                ],
                [
                    'produk_id' => 1,
                    'recency' => 30,
                    'frekuensi' => 1,
                    'qty' => 10,
                    'omzet' => 100000,
                    'cluster' => 3,
                ],
                [
                    'produk_id' => 2,
                    'recency' => 29,
                    'frekuensi' => 2,
                    'qty' => 15,
                    'omzet' => 150000,
                    'cluster' => 3,
                ],
            ],
        ];
    }
}
