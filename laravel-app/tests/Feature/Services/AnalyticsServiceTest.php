<?php

namespace Tests\Feature\Services;

use App\Services\AnalyticsService;
use Illuminate\Http\Client\Request;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;
use UnexpectedValueException;

class AnalyticsServiceTest extends TestCase
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

    public function test_health_mengembalikan_status_service(): void
    {
        Http::fake([
            'http://127.0.0.1:8010/health' => Http::response([
                'status' => 'ok',
                'service' => 'sr12-analytics',
            ]),
        ]);

        $result = app(AnalyticsService::class)
            ->health();

        $this->assertSame([
            'status' => 'ok',
            'service' => 'sr12-analytics',
        ], $result);

        Http::assertSent(
            fn (Request $request): bool => $request->method() === 'GET'
                && $request->url()
                    === 'http://127.0.0.1:8010/health'
        );
    }

    public function test_health_menangani_url_dengan_garis_miring_akhir(): void
    {
        config()->set(
            'services.analytics.url',
            'http://127.0.0.1:8010/'
        );

        Http::fake([
            'http://127.0.0.1:8010/health' => Http::response([
                'status' => 'ok',
                'service' => 'sr12-analytics',
            ]),
        ]);

        app(AnalyticsService::class)->health();

        Http::assertSent(
            fn (Request $request): bool => $request->url()
                    === 'http://127.0.0.1:8010/health'
        );
    }

    public function test_health_menolak_format_response_yang_tidak_sesuai(): void
    {
        Http::fake([
            'http://127.0.0.1:8010/health' => Http::response([
                'status' => 'ok',
            ]),
        ]);

        $this->expectException(
            UnexpectedValueException::class
        );

        $this->expectExceptionMessage(
            'Respons health Analytics Service tidak sesuai kontrak.'
        );

        app(AnalyticsService::class)->health();
    }

    public function test_health_meneruskan_error_http_service(): void
    {
        Http::fake([
            'http://127.0.0.1:8010/health' => Http::response([
                'detail' => 'Internal server error',
            ], 500),
        ]);

        $this->expectException(
            RequestException::class
        );

        app(AnalyticsService::class)->health();
    }
}
