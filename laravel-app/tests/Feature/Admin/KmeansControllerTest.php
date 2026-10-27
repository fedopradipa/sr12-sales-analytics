<?php

use App\Models\DetailKmeans;
use App\Models\DetailTransaksi;
use App\Models\HasilKmeans;
use App\Models\Produk;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it(
    'menampilkan seluruh cluster dalam accordion',
    function (): void {
        $user = User::query()->create([
            'name' => 'Admin KMeans',
            'email' => 'admin-kmeans-ui@example.test',
            'password' => 'password123',
            'role' => 'admin',
        ]);

        $high = Produk::query()->create([
            'kode' => 'KMH001',
            'nama' => 'Produk Cluster Tinggi',
        ]);

        $low = Produk::query()->create([
            'kode' => 'KML001',
            'nama' => 'Produk Cluster Rendah',
        ]);

        $result = HasilKmeans::query()->create([
            'user_id' => $user->id,
            'awal' => '2024-01-01',
            'akhir' => '2024-12-31',
            'iterasi' => 3,
        ]);

        foreach (
            [
                [$high, 1],
                [$low, 3],
            ] as [$product, $cluster]
        ) {
            DetailKmeans::query()->create([
                'kmeans_id' => $result->id,
                'produk_id' => $product->id,
                'recency' => 0,
                'frekuensi' => 4,
                'qty' => 20,
                'omzet' => 200000,
                'cluster' => $cluster,
            ]);
        }

        $this->actingAs($user)
            ->get(
                route(
                    'admin.kmeans.show',
                    [
                        'hasilKmeans' => $result,
                        'cluster' => 3,
                    ]
                )
            )
            ->assertOk()
            ->assertSee('Pergerakan Tinggi')
            ->assertSee('Pergerakan Sedang')
            ->assertSee('Pergerakan Rendah')
            ->assertSee(
                'data-cluster-panel="1"',
                false
            )
            ->assertSee(
                'data-cluster-panel="2"',
                false
            )
            ->assertSee(
                'data-cluster-panel="3"',
                false
            )
            ->assertSee('Produk Cluster Tinggi')
            ->assertSee('Produk Cluster Rendah')
            ->assertSee('Recency (Hari)')
            ->assertSee('Jumlah Hari Terjual')
            ->assertSee('Total Terjual')
            ->assertSee('Total Omzet')
            ->assertDontSee('Prediksi DES');
    }
);

it(
    'form pembuatan segmentasi ditampilkan sebagai modal',
    function (): void {
        $user = User::query()->create([
            'name' => 'Admin Modal KMeans',
            'email' => 'admin-modal-kmeans@example.test',
            'password' => 'password123',
            'role' => 'admin',
        ]);

        $product = Produk::query()->create([
            'kode' => 'KMM001',
            'nama' => 'Produk Modal KMeans',
        ]);

        DetailTransaksi::query()->create([
            'user_id' => $user->id,
            'produk_id' => $product->id,
            'tanggal' => '2024-01-10',
            'faktur' => 'INV-KMM-001',
            'qty' => 2,
            'harga' => 10000,
            'subtotal' => 20000,
        ]);

        $this->actingAs($user)
            ->get(route('admin.kmeans.index'))
            ->assertOk()
            ->assertSee(
                'data-kmeans-modal-open="kmeans-form-modal"',
                false
            )
            ->assertSee(
                'id="kmeans-form-modal"',
                false
            )
            ->assertSee(
                'name="awal"',
                false
            )
            ->assertSee(
                'name="akhir"',
                false
            )
            ->assertSee('Proses Segmentasi');

        $this->actingAs($user);

        $this->followingRedirects()
            ->from(route('admin.kmeans.index'))
            ->post(route('admin.kmeans.store'), [
                'form_context' => 'kmeans',
                'awal' => '2024-02-01',
                'akhir' => '2024-01-01',
            ])
            ->assertOk()
            ->assertSee(
                'data-kmeans-open-on-load="true"',
                false
            )
            ->assertSee(
                'Data periode belum valid.'
            )
            ->assertSee(
                'Periode akhir tidak boleh sebelum periode awal.'
            );
    }
);
