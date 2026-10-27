<?php

namespace App\Services;

use App\Models\Permission;
use App\Models\User;
use DomainException;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;

final class UserService
{
    public function paginate(int $perPage = 10): LengthAwarePaginator
    {
        return User::query()
            ->where('role', 'operator')
            ->with('permissions')
            ->orderBy('name')
            ->orderBy('id')
            ->paginate($perPage);
    }

    /** @return Collection<int, Permission> */
    public function permissions(): Collection
    {
        return Permission::query()
            ->orderBy('name')
            ->get();
    }

    /** @param array<string, mixed> $data */
    public function create(array $data): User
    {
        return DB::transaction(function () use ($data): User {
            $user = User::query()->create([
                'name' => $data['name'],
                'email' => $data['email'],
                'password' => $data['password'],
                'role' => 'operator',
            ]);

            $user->permissions()->sync(
                $this->permissionIds($data)
            );

            return $user->load('permissions');
        }, 3);
    }

    /** @param array<string, mixed> $data */
    public function update(
        User $actor,
        User $target,
        array $data
    ): User {
        return DB::transaction(function () use (
            $actor,
            $target,
            $data
        ): User {
            abort_unless($actor->isAdmin(), 403);

            $target = User::query()
                ->lockForUpdate()
                ->findOrFail($target->id);

            $this->ensureOperator($target);

            $attributes = [
                'name' => $data['name'],
                'email' => $data['email'],
            ];

            if (! empty($data['password'])) {
                $attributes['password'] = $data['password'];
            }

            $target->update($attributes);
            $target->permissions()->sync(
                $this->permissionIds($data)
            );

            return $target->load('permissions');
        }, 3);
    }

    public function delete(User $actor, User $target): void
    {
        DB::transaction(function () use ($actor, $target): void {
            abort_unless($actor->isAdmin(), 403);

            $target = User::query()
                ->lockForUpdate()
                ->findOrFail($target->id);

            $this->ensureOperator($target);

            DB::table('sessions')
                ->where('user_id', $target->id)
                ->delete();

            $target->delete();
        }, 3);
    }

    /**
     * @param  array<string, mixed>  $data
     * @return list<int>
     */
    private function permissionIds(array $data): array
    {
        $ids = array_values(array_unique(array_map(
            'intval',
            $data['permissions'] ?? []
        )));

        if ($ids === []) {
            throw new DomainException(
                'Minimal satu hak akses wajib dipilih.'
            );
        }

        return $ids;
    }

    private function ensureOperator(User $target): void
    {
        if (! $target->isOperator()) {
            throw new DomainException(
                'Halaman ini hanya dapat mengelola akun operator.'
            );
        }
    }
}
