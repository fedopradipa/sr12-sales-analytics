<?php

use App\Models\Permission;
use App\Models\User;
use App\Services\Auth\AuthService;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

function calibrationPermissionUser(
    string $email,
    string $code
): User {
    $user = User::query()->create([
        'name' => 'Operator Hak Akses',
        'email' => $email,
        'password' => 'password123',
        'role' => 'operator',
    ]);

    $permission = Permission::query()->firstOrCreate(
        ['code' => $code],
        [
            'name' => $code === 'kalibrasi'
                ? 'Penentuan Parameter'
                : 'Peramalan Penjualan',
        ]
    );

    $user->permissions()->sync([$permission->id]);

    return $user;
}

it(
    'memisahkan hak akses penentuan parameter dari peramalan',
    function (): void {
        $kalibrasi = calibrationPermissionUser(
            'operator-kalibrasi@example.test',
            'kalibrasi'
        );

        $this->actingAs($kalibrasi)
            ->get(route('admin.kalibrasi-parameter.index'))
            ->assertOk()
            ->assertSeeText('Penentuan Parameter');

        $this->actingAs($kalibrasi)
            ->get(route('admin.des.index'))
            ->assertForbidden();

        $peramalan = calibrationPermissionUser(
            'operator-peramalan@example.test',
            'des'
        );

        $this->actingAs($peramalan)
            ->get(route('admin.des.index'))
            ->assertOk();

        $this->actingAs($peramalan)
            ->get(route('admin.kalibrasi-parameter.index'))
            ->assertForbidden();
    }
);

it(
    'operator hanya penentuan parameter memiliki redirect fitur yang valid',
    function (): void {
        $user = calibrationPermissionUser(
            'operator-kalibrasi-redirect@example.test',
            'kalibrasi'
        );

        expect(
            app(AuthService::class)->redirectPath($user)
        )->toBe(
            route('admin.kalibrasi-parameter.index')
        );
    }
);

it(
    'hak akses penentuan parameter tersedia pada kelola pengguna',
    function (): void {
        $admin = User::query()->create([
            'name' => 'Admin Hak Akses',
            'email' => 'admin-hak-akses@example.test',
            'password' => 'password123',
            'role' => 'admin',
        ]);

        $this->actingAs($admin)
            ->get(route('admin.users.create'))
            ->assertOk()
            ->assertSeeText('Penentuan Parameter');
    }
);
