<?php

use App\Models\DetailTransaksi;
use App\Models\HasilKmeans;
use App\Models\Permission;
use App\Models\Produk;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;

uses(RefreshDatabase::class);

function createCrudTransactionUser(): User
{
    $user = User::query()->create([
        'name' => 'CRUD Transaksi '.Str::random(6),
        'email' => Str::uuid().'@example.test',
        'password' => 'password',
        'role' => 'operator',
    ]);

    $permission = Permission::query()->firstOrCreate(
        [
            'code' => 'transaksi',
        ],
        [
            'name' => 'Kelola Transaksi',
        ]
    );

    $user->permissions()->syncWithoutDetaching([
        $permission->id,
    ]);

    return $user;
}

function createCrudTransactionProduct(
    string $kode
): Produk {
    return Produk::query()->create([
        'kode' => $kode,
        'nama' => 'Produk '.Str::random(6),
    ]);
}

function createCrudTransaction(
    User $user,
    Produk $produk,
    string $tanggal = '2026-01-10',
    string $faktur = 'INV-CRUD-001',
    int $qty = 2,
    int $harga = 50000,
    int $subtotal = 100000
): DetailTransaksi {
    return DetailTransaksi::query()->create([
        'user_id' => $user->id,
        'produk_id' => $produk->id,
        'tanggal' => $tanggal,
        'faktur' => $faktur,
        'qty' => $qty,
        'harga' => $harga,
        'subtotal' => $subtotal,
    ]);
}

it(
    'menampilkan aksi edit hapus dan hapus semua transaksi',
    function (): void {
        $user = createCrudTransactionUser();
        $produk = createCrudTransactionProduct(
            'CRUD001'
        );

        createCrudTransaction(
            $user,
            $produk
        );

        $this
            ->actingAs($user)
            ->get(
                route('admin.transaksi.index')
            )
            ->assertOk()
            ->assertSee('Edit')
            ->assertSee(
                'data-transaction-edit-open',
                false
            )
            ->assertSee(
                'id="transaction-edit-modal"',
                false
            )
            ->assertSee(
                'm-auto w-[calc(100%_-_2rem)] max-w-2xl',
                false
            )
            ->assertSee(
                'backdrop:backdrop-blur-sm',
                false
            )
            ->assertSee('Hapus')
            ->assertSee('Hapus Semua');
    }
);

it(
    'dapat memperbarui transaksi',
    function (): void {
        $user = createCrudTransactionUser();

        $produkAwal = createCrudTransactionProduct(
            'CRUD002'
        );

        $produkBaru = createCrudTransactionProduct(
            'CRUD003'
        );

        $transaksi = createCrudTransaction(
            $user,
            $produkAwal,
            tanggal: '2026-01-10',
            faktur: 'INV-CRUD-002'
        );

        $this
            ->actingAs($user)
            ->put(
                route(
                    'admin.transaksi.update',
                    $transaksi
                ),
                [
                    'tanggal' => '2026-01-15',
                    'faktur' => 'INV-CRUD-EDIT',
                    'produk_id' => $produkBaru->id,
                    'qty' => 3,
                    'harga' => 60000,
                    'subtotal' => 999,
                ]
            )
            ->assertSessionHasNoErrors()
            ->assertRedirect(
                route('admin.transaksi.index')
            )
            ->assertSessionHas(
                'success',
                'Transaksi berhasil diperbarui.'
            );

        $this->assertDatabaseHas(
            'detail_transaksi',
            [
                'id' => $transaksi->id,
                'user_id' => $user->id,
                'produk_id' => $produkBaru->id,
                'tanggal' => '2026-01-15 00:00:00',
                'faktur' => 'INV-CRUD-EDIT',
                'qty' => 3,
                'harga' => 60000,
                'subtotal' => 180000,
            ]
        );
    }
);

it(
    'menolak edit yang membuat transaksi duplikat',
    function (): void {
        $user = createCrudTransactionUser();

        $produk = createCrudTransactionProduct(
            'CRUD004'
        );

        createCrudTransaction(
            $user,
            $produk,
            tanggal: '2026-02-01',
            faktur: 'INV-DUP-001'
        );

        $transaksiKedua = createCrudTransaction(
            $user,
            $produk,
            tanggal: '2026-02-02',
            faktur: 'INV-DUP-002'
        );

        $this
            ->actingAs($user)
            ->from(
                route('admin.transaksi.index')
            )
            ->put(
                route(
                    'admin.transaksi.update',
                    $transaksiKedua
                ),
                [
                    'tanggal' => '2026-02-01',
                    'faktur' => 'INV-DUP-001',
                    'produk_id' => $produk->id,
                    'qty' => 5,
                    'harga' => 50000,
                    'subtotal' => 250000,
                ]
            )
            ->assertSessionHasErrors(
                'produk_id'
            );

        $this->assertDatabaseHas(
            'detail_transaksi',
            [
                'id' => $transaksiKedua->id,
                'tanggal' => '2026-02-02 00:00:00',
                'faktur' => 'INV-DUP-002',
            ]
        );
    }
);

it(
    'dapat menghapus satu transaksi',
    function (): void {
        $user = createCrudTransactionUser();

        $produk = createCrudTransactionProduct(
            'CRUD005'
        );

        $transaksiDihapus = createCrudTransaction(
            $user,
            $produk,
            tanggal: '2026-03-01',
            faktur: 'INV-DEL-001'
        );

        $transaksiTetap = createCrudTransaction(
            $user,
            $produk,
            tanggal: '2026-03-02',
            faktur: 'INV-DEL-002'
        );

        $this
            ->actingAs($user)
            ->delete(
                route(
                    'admin.transaksi.destroy',
                    $transaksiDihapus
                )
            )
            ->assertSessionHasNoErrors()
            ->assertRedirect(
                route('admin.transaksi.index')
            )
            ->assertSessionHas(
                'success',
                'Transaksi berhasil dihapus.'
            );

        $this->assertDatabaseMissing(
            'detail_transaksi',
            [
                'id' => $transaksiDihapus->id,
            ]
        );

        $this->assertDatabaseHas(
            'detail_transaksi',
            [
                'id' => $transaksiTetap->id,
            ]
        );
    }
);

it(
    'dapat menghapus seluruh transaksi',
    function (): void {
        $user = createCrudTransactionUser();

        $produkSatu = createCrudTransactionProduct(
            'CRUD006'
        );

        $produkDua = createCrudTransactionProduct(
            'CRUD007'
        );

        createCrudTransaction(
            $user,
            $produkSatu,
            tanggal: '2026-04-01',
            faktur: 'INV-ALL-001'
        );

        createCrudTransaction(
            $user,
            $produkDua,
            tanggal: '2026-04-02',
            faktur: 'INV-ALL-002'
        );

        $this->assertDatabaseCount(
            'detail_transaksi',
            2
        );

        $this
            ->actingAs($user)
            ->delete(
                route(
                    'admin.transaksi.destroy-all'
                )
            )
            ->assertSessionHasNoErrors()
            ->assertRedirect(
                route('admin.transaksi.index')
            );

        $this->assertDatabaseCount(
            'detail_transaksi',
            0
        );
    }
);

it(
    'tetap dapat memperbarui transaksi ketika hasil analitik sudah tersimpan',
    function (): void {
        $user = createCrudTransactionUser();

        $produk = createCrudTransactionProduct(
            'CRUD008'
        );

        $transaksi = createCrudTransaction(
            $user,
            $produk,
            tanggal: '2026-05-01',
            faktur: 'INV-ANALITIK-EDIT'
        );

        $hasilKmeans = HasilKmeans::query()->create([
            'user_id' => $user->id,
            'awal' => '2026-05-01',
            'akhir' => '2026-05-31',
            'iterasi' => 3,
        ]);

        $this
            ->actingAs($user)
            ->put(
                route(
                    'admin.transaksi.update',
                    $transaksi
                ),
                [
                    'tanggal' => '2026-05-02',
                    'faktur' => 'INV-ANALITIK-EDIT-BARU',
                    'produk_id' => $produk->id,
                    'qty' => 3,
                    'harga' => 50000,
                    'subtotal' => 999,
                ]
            )
            ->assertSessionHasNoErrors()
            ->assertRedirect(
                route('admin.transaksi.index')
            );

        $this->assertDatabaseHas(
            'detail_transaksi',
            [
                'id' => $transaksi->id,
                'tanggal' => '2026-05-02 00:00:00',
                'faktur' => 'INV-ANALITIK-EDIT-BARU',
                'qty' => 3,
                'harga' => 50000,
                'subtotal' => 150000,
            ]
        );

        $this->assertDatabaseHas(
            'hasil_kmeans',
            [
                'id' => $hasilKmeans->id,
            ]
        );
    }
);

it(
    'tetap dapat menghapus transaksi ketika hasil analitik sudah tersimpan',
    function (): void {
        $user = createCrudTransactionUser();

        $produk = createCrudTransactionProduct(
            'CRUD009'
        );

        $transaksi = createCrudTransaction(
            $user,
            $produk,
            tanggal: '2026-06-01',
            faktur: 'INV-ANALITIK-HAPUS'
        );

        $hasilKmeans = HasilKmeans::query()->create([
            'user_id' => $user->id,
            'awal' => '2026-06-01',
            'akhir' => '2026-06-30',
            'iterasi' => 3,
        ]);

        $this
            ->actingAs($user)
            ->delete(
                route(
                    'admin.transaksi.destroy',
                    $transaksi
                )
            )
            ->assertSessionHasNoErrors()
            ->assertRedirect(
                route('admin.transaksi.index')
            );

        $this->assertDatabaseMissing(
            'detail_transaksi',
            [
                'id' => $transaksi->id,
            ]
        );

        $this->assertDatabaseHas(
            'hasil_kmeans',
            [
                'id' => $hasilKmeans->id,
            ]
        );
    }
);

it(
    'tetap dapat menghapus seluruh transaksi ketika hasil analitik sudah tersimpan',
    function (): void {
        $user = createCrudTransactionUser();

        $produk = createCrudTransactionProduct(
            'CRUD010'
        );

        createCrudTransaction(
            $user,
            $produk,
            tanggal: '2026-07-01',
            faktur: 'INV-ANALITIK-ALL-001'
        );

        createCrudTransaction(
            $user,
            $produk,
            tanggal: '2026-07-02',
            faktur: 'INV-ANALITIK-ALL-002'
        );

        $hasilKmeans = HasilKmeans::query()->create([
            'user_id' => $user->id,
            'awal' => '2026-07-01',
            'akhir' => '2026-07-31',
            'iterasi' => 3,
        ]);

        $this
            ->actingAs($user)
            ->delete(
                route(
                    'admin.transaksi.destroy-all'
                )
            )
            ->assertSessionHasNoErrors()
            ->assertRedirect(
                route('admin.transaksi.index')
            );

        $this->assertDatabaseCount(
            'detail_transaksi',
            0
        );

        $this->assertDatabaseHas(
            'hasil_kmeans',
            [
                'id' => $hasilKmeans->id,
            ]
        );
    }
);
