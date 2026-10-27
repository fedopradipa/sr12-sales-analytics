<?php

use App\Models\Permission;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

function loginRedirectPermission(
    string $code
): Permission {
    return Permission::query()->firstOrCreate(
        ['code' => $code],
        ['name' => ucfirst($code)]
    );
}

it('mengarahkan admin dan operator yang memiliki card ke dashboard', function (): void {
    $admin = User::query()->create([
        'name' => 'Admin Login',
        'email' => 'admin-login@example.test',
        'password' => 'password123',
        'role' => 'admin',
    ]);

    $this->post(route('login.store'), [
        'email' => $admin->email,
        'password' => 'password123',
    ])->assertRedirect(
        route('admin.dashboard')
    );

    auth()->logout();

    $operator = User::query()->create([
        'name' => 'Operator Login',
        'email' => 'operator-login@example.test',
        'password' => 'password123',
        'role' => 'operator',
    ]);

    $permission = loginRedirectPermission(
        'transaksi'
    );

    $operator->permissions()->sync([
        $permission->id,
    ]);

    $this->post(route('login.store'), [
        'email' => $operator->email,
        'password' => 'password123',
    ])->assertRedirect(
        route('admin.dashboard')
    );
});

it('operator yang hanya memiliki fitur tanpa card diarahkan ke fitur tersebut', function (): void {
    $operator = User::query()->create([
        'name' => 'Operator Laporan',
        'email' => 'operator-laporan@example.test',
        'password' => 'password123',
        'role' => 'operator',
    ]);

    $permission = loginRedirectPermission(
        'laporan.prediksi'
    );

    $operator->permissions()->sync([
        $permission->id,
    ]);

    $this->post(route('login.store'), [
        'email' => $operator->email,
        'password' => 'password123',
    ])->assertRedirect(
        route(
            'admin.laporan.peramalan.index'
        )
    );
});

it('menolak login operator yang tidak memiliki hak akses', function (): void {
    $operator = User::query()->create([
        'name' => 'Operator Kosong',
        'email' => 'operator-kosong@example.test',
        'password' => 'password123',
        'role' => 'operator',
    ]);

    $this->post(route('login.store'), [
        'email' => $operator->email,
        'password' => 'password123',
    ])->assertSessionHasErrors('email');

    $this->assertGuest();
});

it('halaman login menggunakan branding Distributor SR12 tanpa register', function (): void {
    $this
        ->get(route('login'))
        ->assertOk()
        ->assertSee('Distributor SR12')
        ->assertSee('Sistem Analitik Penjualan')
        ->assertSee('Segmentasi Produk K-Means')
        ->assertSee('Peramalan Penjualan DES Brown')
        ->assertSee('Masuk')
        ->assertDontSee('Register')
        ->assertDontSee('Daftar Akun');
});

it(
    'url fitur bersama tidak menggunakan prefix admin dan pengguna tetap area admin',
    function (): void {
        expect(
            parse_url(
                route('admin.dashboard'),
                PHP_URL_PATH
            )
        )->toBe('/dashboard');

        expect(
            parse_url(
                route('admin.produk.index'),
                PHP_URL_PATH
            )
        )->toBe('/produk');

        expect(
            parse_url(
                route('admin.transaksi.index'),
                PHP_URL_PATH
            )
        )->toBe('/transaksi');

        expect(
            parse_url(
                route('admin.kmeans.index'),
                PHP_URL_PATH
            )
        )->toBe('/segmentasi');

        expect(
            parse_url(
                route('admin.des.index'),
                PHP_URL_PATH
            )
        )->toBe('/peramalan');

        expect(
            parse_url(
                route('admin.laporan.index'),
                PHP_URL_PATH
            )
        )->toBe('/laporan');

        expect(
            parse_url(
                route('admin.laporan.peramalan.index'),
                PHP_URL_PATH
            )
        )->toBe('/laporan/peramalan');

        expect(
            parse_url(
                route('admin.users.index'),
                PHP_URL_PATH
            )
        )->toBe('/admin/pengguna');
    }
);
