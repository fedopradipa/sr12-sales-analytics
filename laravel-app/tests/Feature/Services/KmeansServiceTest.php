<?php

namespace Tests\Feature\Services;

use App\Models\DetailTransaksi;
use App\Models\HasilKmeans;
use App\Models\Produk;
use App\Models\User;
use App\Services\KmeansService;
use Carbon\CarbonImmutable;
use DomainException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use InvalidArgumentException;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    config()->set([
        'services.analytics.url' => 'http://127.0.0.1:8010',
        'services.analytics.connect_timeout' => 3,
        'services.analytics.timeout' => 10,
    ]);

    Http::preventStrayRequests();
});

function createKmeansUser(): User
{
    return User::query()->create([
        'name' => 'K-Means User',
        'email' => Str::uuid().'@example.test',
        'password' => 'password',
        'role' => 'admin',
    ]);
}

function createKmeansProduct(
    string $kode,
    string $nama
): Produk {
    return Produk::query()->create([
        'kode' => $kode,
        'nama' => $nama,
    ]);
}

function createKmeansTransaction(
    User $user,
    Produk $produk,
    string $tanggal,
    string $faktur,
    int $qty,
    int $harga
): DetailTransaksi {
    return DetailTransaksi::query()->create([
        'user_id' => $user->id,
        'produk_id' => $produk->id,
        'tanggal' => $tanggal,
        'faktur' => $faktur,
        'qty' => $qty,
        'harga' => $harga,
        'subtotal' => $qty * $harga,
    ]);
}

it(
    'menghitung atribut harian dan menyimpan hasil kmeans',
    function (): void {
        $user = createKmeansUser();

        $produkA = createKmeansProduct(
            'KM001',
            'Produk A'
        );

        $produkB = createKmeansProduct(
            'KM002',
            'Produk B'
        );

        $produkC = createKmeansProduct(
            'KM003',
            'Produk C'
        );

        /*
         * Produk A memiliki dua faktur pada hari yang sama.
         * Frekuensinya tetap satu hari untuk tanggal tersebut.
         */
        createKmeansTransaction(
            $user,
            $produkA,
            '2026-01-02',
            'INV-KM-001',
            2,
            10000
        );

        createKmeansTransaction(
            $user,
            $produkA,
            '2026-01-02',
            'INV-KM-002',
            3,
            10000
        );

        createKmeansTransaction(
            $user,
            $produkA,
            '2026-01-03',
            'INV-KM-003',
            4,
            10000
        );

        createKmeansTransaction(
            $user,
            $produkB,
            '2026-01-04',
            'INV-KM-004',
            10,
            20000
        );

        createKmeansTransaction(
            $user,
            $produkC,
            '2026-01-05',
            'INV-KM-005',
            20,
            30000
        );

        /*
         * Transaksi di luar periode tidak boleh ikut dihitung.
         */
        createKmeansTransaction(
            $user,
            $produkC,
            '2026-02-01',
            'INV-KM-006',
            100,
            30000
        );

        Http::fake([
            'http://127.0.0.1:8010/api/kmeans/analyze' => function (Request $request) use (
                $produkA,
                $produkB,
                $produkC
            ) {
                expect($request->data())->toBe([
                    'products' => [
                        [
                            'produk_id' => $produkA->id,
                            'recency' => 28,
                            'frekuensi' => 2,
                            'qty' => 9,
                            'omzet' => 90000,
                        ],
                        [
                            'produk_id' => $produkB->id,
                            'recency' => 27,
                            'frekuensi' => 1,
                            'qty' => 10,
                            'omzet' => 200000,
                        ],
                        [
                            'produk_id' => $produkC->id,
                            'recency' => 26,
                            'frekuensi' => 1,
                            'qty' => 20,
                            'omzet' => 600000,
                        ],
                    ],
                ]);

                return Http::response([
                    'k' => 3,
                    'iterasi' => 2,
                    'results' => [
                        [
                            'produk_id' => $produkC->id,
                            'recency' => 26,
                            'frekuensi' => 1,
                            'qty' => 20,
                            'omzet' => 600000,
                            'cluster' => 1,
                        ],
                        [
                            'produk_id' => $produkB->id,
                            'recency' => 27,
                            'frekuensi' => 1,
                            'qty' => 10,
                            'omzet' => 200000,
                            'cluster' => 2,
                        ],
                        [
                            'produk_id' => $produkA->id,
                            'recency' => 28,
                            'frekuensi' => 2,
                            'qty' => 9,
                            'omzet' => 90000,
                            'cluster' => 3,
                        ],
                    ],
                ]);
            },
        ]);

        $hasil = app(KmeansService::class)->analyze(
            $user,
            CarbonImmutable::parse('2026-01-01'),
            CarbonImmutable::parse('2026-01-31')
        );

        expect($hasil)
            ->toBeInstanceOf(HasilKmeans::class)
            ->and($hasil->user_id)->toBe($user->id)
            ->and($hasil->awal->toDateString())
            ->toBe('2026-01-01')
            ->and($hasil->akhir->toDateString())
            ->toBe('2026-01-31')
            ->and($hasil->iterasi)->toBe(2)
            ->and($hasil->detail)->toHaveCount(3);

        $this->assertDatabaseHas('detail_kmeans', [
            'kmeans_id' => $hasil->id,
            'produk_id' => $produkA->id,
            'recency' => 28,
            'frekuensi' => 2,
            'qty' => 9,
            'omzet' => 90000,
            'cluster' => 3,
        ]);

        $this->assertDatabaseHas('detail_kmeans', [
            'kmeans_id' => $hasil->id,
            'produk_id' => $produkB->id,
            'recency' => 27,
            'frekuensi' => 1,
            'qty' => 10,
            'omzet' => 200000,
            'cluster' => 2,
        ]);

        $this->assertDatabaseHas('detail_kmeans', [
            'kmeans_id' => $hasil->id,
            'produk_id' => $produkC->id,
            'recency' => 26,
            'frekuensi' => 1,
            'qty' => 20,
            'omzet' => 600000,
            'cluster' => 1,
        ]);

        Http::assertSentCount(1);
    }
);

it(
    'menolak periode awal setelah periode akhir',
    function (): void {
        $user = createKmeansUser();

        expect(
            fn () => app(KmeansService::class)->analyze(
                $user,
                CarbonImmutable::parse('2026-02-01'),
                CarbonImmutable::parse('2026-01-01')
            )
        )->toThrow(
            InvalidArgumentException::class,
            'Periode awal tidak boleh melebihi periode akhir.'
        );

        Http::assertNothingSent();

        $this->assertDatabaseCount(
            'hasil_kmeans',
            0
        );
    }
);

it(
    'menolak periode dengan kurang dari tiga produk',
    function (): void {
        $user = createKmeansUser();

        $produkA = createKmeansProduct(
            'LIMIT001',
            'Produk Terbatas A'
        );

        $produkB = createKmeansProduct(
            'LIMIT002',
            'Produk Terbatas B'
        );

        createKmeansTransaction(
            $user,
            $produkA,
            '2026-01-01',
            'INV-LIMIT-001',
            1,
            10000
        );

        createKmeansTransaction(
            $user,
            $produkB,
            '2026-01-02',
            'INV-LIMIT-002',
            1,
            10000
        );

        expect(
            fn () => app(KmeansService::class)->analyze(
                $user,
                CarbonImmutable::parse('2026-01-01'),
                CarbonImmutable::parse('2026-01-31')
            )
        )->toThrow(
            DomainException::class,
            'Periode analisis harus memiliki transaksi dari minimal 3 produk.'
        );

        Http::assertNothingSent();

        $this->assertDatabaseCount(
            'hasil_kmeans',
            0
        );
    }
);

it(
    'tidak menyimpan hasil ketika analytics service gagal',
    function (): void {
        $user = createKmeansUser();

        foreach (range(1, 3) as $number) {
            $produk = createKmeansProduct(
                'FAIL'.str_pad(
                    (string) $number,
                    3,
                    '0',
                    STR_PAD_LEFT
                ),
                'Produk Gagal '.$number
            );

            createKmeansTransaction(
                $user,
                $produk,
                '2026-01-0'.$number,
                'INV-FAIL-00'.$number,
                $number,
                10000
            );
        }

        Http::fake([
            'http://127.0.0.1:8010/api/kmeans/analyze' => Http::response([
                'detail' => 'Service gagal.',
            ], 500),
        ]);

        expect(
            fn () => app(KmeansService::class)->analyze(
                $user,
                CarbonImmutable::parse('2026-01-01'),
                CarbonImmutable::parse('2026-01-31')
            )
        )->toThrow(RequestException::class);

        $this->assertDatabaseCount(
            'hasil_kmeans',
            0
        );

        $this->assertDatabaseCount(
            'detail_kmeans',
            0
        );
    }
);
