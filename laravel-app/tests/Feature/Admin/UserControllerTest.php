<?php

use App\Models\Permission;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

uses(RefreshDatabase::class);

function userManagementActor(string $role = 'admin'): User
{
    return User::query()->create([
        'name' => ucfirst($role).' Test',
        'email' => Str::uuid().'@example.test',
        'password' => 'password123',
        'role' => $role,
    ]);
}

function userManagementPermission(string $code): Permission
{
    return Permission::query()->firstOrCreate(
        ['code' => $code],
        ['name' => ucfirst($code)]
    );
}

it('hanya menampilkan operator tanpa kolom role dan status', function (): void {
    $admin = userManagementActor();
    $operator = userManagementActor('operator');

    $this->actingAs($admin)
        ->get(route('admin.users.index'))
        ->assertOk()
        ->assertSee($operator->email)
        ->assertDontSee($admin->email)
        ->assertDontSee('Nonaktifkan')
        ->assertDontSee('Aktifkan');
});

it('selalu membuat operator walaupun request membawa role admin', function (): void {
    $admin = userManagementActor();
    $permission = userManagementPermission('produk');

    $this->actingAs($admin)
        ->post(route('admin.users.store'), [
            'name' => 'Operator Baru',
            'email' => 'operator-baru@example.test',
            'password' => 'password123',
            'password_confirmation' => 'password123',
            'role' => 'admin',
            'permissions' => [$permission->id],
        ])
        ->assertRedirect(route('admin.users.index'));

    $operator = User::query()
        ->where('email', 'operator-baru@example.test')
        ->firstOrFail();

    expect($operator->role)->toBe('operator')
        ->and(Hash::check('password123', $operator->password))->toBeTrue()
        ->and($operator->permissions()->pluck('permissions.id')->all())
        ->toBe([$permission->id]);
});

it('memperbarui operator tanpa dapat mengubah role', function (): void {
    $admin = userManagementActor();
    $operator = userManagementActor('operator');
    $permission = userManagementPermission(
        'laporan.segmentasi'
    );

    $this->actingAs($admin)
        ->put(route('admin.users.update', $operator), [
            'name' => 'Operator Diperbarui',
            'email' => $operator->email,
            'password' => '',
            'password_confirmation' => '',
            'role' => 'admin',
            'permissions' => [$permission->id],
        ])
        ->assertRedirect(route('admin.users.index'));

    expect($operator->refresh()->name)->toBe('Operator Diperbarui')
        ->and($operator->role)->toBe('operator')
        ->and($operator->permissions()->pluck('permissions.id')->all())
        ->toBe([$permission->id]);
});

it('menghapus operator secara soft delete dan memutus sesi aktifnya', function (): void {
    $admin = userManagementActor();
    $operator = userManagementActor('operator');

    DB::table('sessions')->insert([
        'id' => Str::random(40),
        'user_id' => $operator->id,
        'ip_address' => '127.0.0.1',
        'user_agent' => 'Pest',
        'payload' => base64_encode('test'),
        'last_activity' => now()->timestamp,
    ]);

    $this->actingAs($admin)
        ->delete(route('admin.users.destroy', $operator))
        ->assertRedirect(route('admin.users.index'));

    $this->assertSoftDeleted('users', ['id' => $operator->id]);
    expect(DB::table('sessions')->where('user_id', $operator->id)->exists())
        ->toBeFalse();
});

it('operator tidak dapat mengelola pengguna', function (): void {
    $operator = userManagementActor('operator');

    $this->actingAs($operator)
        ->get(route('admin.users.index'))
        ->assertForbidden();
});
