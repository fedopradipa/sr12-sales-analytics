<?php

use App\Models\Produk;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

function produkTestAdmin(
    string $suffix
): User {
    return User::query()->create([
        'name' => 'Admin Produk '.$suffix,
        'email' => 'admin-produk-'
            .$suffix
            .'@example.test',
        'password' => 'password123',
        'role' => 'admin',
    ]);
}

it(
    'menyimpan stok awal dan stok minimum saat produk dibuat',
    function (): void {
        $admin = produkTestAdmin('create');

        $this
            ->actingAs($admin)
            ->post(
                route('admin.produk.store'),
                [
                    'kode' => 'sr001',
                    'nama' => 'Produk Satu',
                    'stok' => 25,
                    'stok_min' => 10,
                ]
            )
            ->assertRedirect(
                route('admin.produk.index')
            );

        $produk = Produk::query()
            ->where('kode', 'SR001')
            ->firstOrFail();

        expect($produk->stok)
            ->toBe(25);

        expect($produk->stok_min)
            ->toBe(10);

        expect($produk->stok_at)
            ->not
            ->toBeNull();
    }
);

it(
    'edit produk tidak dapat mengubah stok saat ini',
    function (): void {
        $admin = produkTestAdmin('edit');

        $produk = Produk::query()->create([
            'kode' => 'SR002',
            'nama' => 'Produk Dua',
            'stok' => 40,
            'stok_min' => 5,
            'stok_at' => '2026-08-01 10:00:00',
        ]);

        $waktuStok = $produk
            ->stok_at
            ->toDateTimeString();

        $this
            ->actingAs($admin)
            ->put(
                route(
                    'admin.produk.update',
                    $produk
                ),
                [
                    'kode' => 'SR002',
                    'nama' => 'Produk Dua Baru',

                    // Sengaja dikirim.
                    // Field ini tidak boleh mengubah stok.
                    'stok' => 999,

                    'stok_min' => 8,
                ]
            )
            ->assertRedirect(
                route('admin.produk.index')
            );

        $produk->refresh();

        expect($produk->nama)
            ->toBe('Produk Dua Baru');

        expect($produk->stok)
            ->toBe(40);

        expect($produk->stok_min)
            ->toBe(8);

        expect(
            $produk
                ->stok_at
                ->toDateTimeString()
        )->toBe($waktuStok);
    }
);

it(
    'memperbarui stok hanya melalui endpoint khusus',
    function (): void {
        $admin = produkTestAdmin('stock');

        $produk = Produk::query()->create([
            'kode' => 'SR003',
            'nama' => 'Produk Tiga',
            'stok' => 12,
            'stok_min' => 4,
            'stok_at' => null,
        ]);

        $this
            ->actingAs($admin)
            ->put(
                route(
                    'admin.produk.stok.update',
                    $produk
                ),
                [
                    'stok' => 31,
                ]
            )
            ->assertRedirect(
                route('admin.produk.index')
            );

        $produk->refresh();

        expect($produk->stok)
            ->toBe(31);

        expect($produk->stok_min)
            ->toBe(4);

        expect($produk->stok_at)
            ->not
            ->toBeNull();
    }
);

it(
    'menolak nilai stok negatif',
    function (): void {
        $admin = produkTestAdmin('negative');

        $produk = Produk::query()->create([
            'kode' => 'SR004',
            'nama' => 'Produk Empat',
            'stok' => 15,
            'stok_min' => 5,
        ]);

        $this
            ->actingAs($admin)
            ->from(route('admin.produk.index'))
            ->put(
                route(
                    'admin.produk.stok.update',
                    $produk
                ),
                [
                    'stok' => -1,
                ]
            )
            ->assertRedirect(
                route('admin.produk.index')
            )
            ->assertSessionHasErrors('stok');

        expect(
            $produk->fresh()->stok
        )->toBe(15);
    }
);

it(
    'menampilkan informasi persediaan pada daftar produk',
    function (): void {
        $admin = produkTestAdmin('index');

        Produk::query()->create([
            'kode' => 'SR005',
            'nama' => 'Produk Lima',
            'stok' => 16,
            'stok_min' => 7,
            'stok_at' => '2026-08-09 12:30:00',
        ]);

        $this
            ->actingAs($admin)
            ->get(route('admin.produk.index'))
            ->assertOk()
            ->assertSee('Stok Saat Ini')
            ->assertSee('Stok Minimum')
            ->assertSee('Pembaruan Stok')
            ->assertSee('Perbarui Stok')
            ->assertSee(
                'data-stock-modal-open',
                false
            )
            ->assertSee(
                'id="produk-stock-modal"',
                false
            )
            ->assertSee('SR005')
            ->assertSee('Produk Lima');
    }
);
