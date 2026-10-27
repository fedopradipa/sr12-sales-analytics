<?php

use App\Models\DetailTransaksi;
use App\Models\Permission;
use App\Models\Produk;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

function createProductCodeLockUser(): User
{
    $user = User::query()->create([
        'name' => 'Operator Produk',
        'email' => 'produk-lock@example.test',
        'password' => 'password',
        'role' => 'operator',
    ]);

    $permission = Permission::query()->firstOrCreate(
        [
            'code' => 'produk',
        ],
        [
            'name' => 'Kelola Produk',
        ]
    );

    $user->permissions()->syncWithoutDetaching([
        $permission->id,
    ]);

    return $user;
}

function createProductCodeLockProduct(
    string $kode = 'SR-LOCK-001'
): Produk {
    return Produk::query()->create([
        'kode' => $kode,
        'nama' => 'Produk Awal',
        'stok' => 20,
        'stok_min' => 5,
        'stok_at' => now(),
    ]);
}

function createProductCodeLockTransaction(
    User $user,
    Produk $produk
): DetailTransaksi {
    return DetailTransaksi::query()->create([
        'user_id' => $user->id,
        'produk_id' => $produk->id,
        'tanggal' => '2026-01-10',
        'faktur' => 'INV-LOCK-001',
        'qty' => 2,
        'harga' => 50000,
        'subtotal' => 100000,
    ]);
}

it(
    'kode produk dapat diubah sebelum pernah digunakan dalam transaksi',
    function (): void {
        $user = createProductCodeLockUser();

        $produk = createProductCodeLockProduct();

        $this
            ->actingAs($user)
            ->put(
                route(
                    'admin.produk.update',
                    $produk
                ),
                [
                    'kode' => 'SR-LOCK-002',
                    'nama' => 'Produk Awal',
                    'stok_min' => 5,
                ]
            )
            ->assertSessionHasNoErrors()
            ->assertRedirect(
                route('admin.produk.index')
            );

        $this->assertDatabaseHas(
            'produk',
            [
                'id' => $produk->id,
                'kode' => 'SR-LOCK-002',
            ]
        );
    }
);

it(
    'kode produk tidak dapat diubah setelah digunakan dalam transaksi',
    function (): void {
        $user = createProductCodeLockUser();

        $produk = createProductCodeLockProduct();

        createProductCodeLockTransaction(
            $user,
            $produk
        );

        $this
            ->actingAs($user)
            ->from(
                route('admin.produk.index')
            )
            ->put(
                route(
                    'admin.produk.update',
                    $produk
                ),
                [
                    'kode' => 'SR-LOCK-BARU',
                    'nama' => 'Produk Awal',
                    'stok_min' => 5,
                ]
            )
            ->assertSessionHasErrors(
                'kode'
            );

        $this->assertDatabaseHas(
            'produk',
            [
                'id' => $produk->id,
                'kode' => 'SR-LOCK-001',
            ]
        );

        $this->assertDatabaseMissing(
            'produk',
            [
                'id' => $produk->id,
                'kode' => 'SR-LOCK-BARU',
            ]
        );
    }
);

it(
    'nama dan stok minimum tetap dapat diubah setelah produk memiliki transaksi',
    function (): void {
        $user = createProductCodeLockUser();

        $produk = createProductCodeLockProduct();

        createProductCodeLockTransaction(
            $user,
            $produk
        );

        $this
            ->actingAs($user)
            ->put(
                route(
                    'admin.produk.update',
                    $produk
                ),
                [
                    'kode' => 'SR-LOCK-001',
                    'nama' => 'Nama Produk Baru',
                    'stok_min' => 12,
                ]
            )
            ->assertSessionHasNoErrors()
            ->assertRedirect(
                route('admin.produk.index')
            );

        $this->assertDatabaseHas(
            'produk',
            [
                'id' => $produk->id,
                'kode' => 'SR-LOCK-001',
                'nama' => 'Nama Produk Baru',
                'stok_min' => 12,
                'stok' => 20,
            ]
        );
    }
);
