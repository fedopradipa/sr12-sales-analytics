@php
    $editing = $user !== null;
    $selectedPermissions = old(
        'permissions',
        $editing ? $user->permissions->pluck('id')->all() : []
    );
@endphp

<x-layouts.app :title="$editing ? 'Edit Operator' : 'Tambah Operator'">
    <div class="sr12-page space-y-6">
        @if (session('error'))
            <div class="rounded border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-800" role="alert">
                {{ session('error') }}
            </div>
        @endif

        @if ($errors->any())
            <div class="rounded border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-800" role="alert">
                <p class="font-semibold">Data operator belum valid.</p>
                <ul class="mt-2 list-disc pl-5">
                    @foreach ($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        <div class="flex flex-wrap items-center justify-between gap-4">
            <div>
                <h1 class="text-xl font-semibold text-gray-900">
                    {{ $editing ? 'Edit Operator' : 'Tambah Operator' }}
                </h1>
                <p class="mt-1 text-sm text-gray-600">
                    Semua akun yang dibuat melalui halaman ini otomatis menjadi operator.
                </p>
            </div>

            <a
                href="{{ route('admin.users.index') }}"
                class="rounded border border-gray-300 bg-white px-4 py-2 text-sm font-semibold text-gray-700 hover:bg-gray-50"
            >
                Kembali
            </a>
        </div>

        <form
            action="{{ $editing ? route('admin.users.update', $user) : route('admin.users.store') }}"
            method="POST"
            class="space-y-6"
            data-submit-once
        >
            @csrf
            @if ($editing)
                @method('PUT')
            @endif

            <section class="rounded-lg bg-white p-6 shadow">
                <div class="grid gap-5 md:grid-cols-2">
                    <div>
                        <label for="name" class="mb-2 block text-sm font-medium text-gray-700">
                            Nama
                        </label>
                        <input
                            id="name"
                            name="name"
                            type="text"
                            value="{{ old('name', $user?->name) }}"
                            class="w-full rounded border border-gray-300 px-3 py-2 text-sm"
                        >
                    </div>

                    <div>
                        <label for="email" class="mb-2 block text-sm font-medium text-gray-700">
                            Email
                        </label>
                        <input
                            id="email"
                            name="email"
                            type="email"
                            value="{{ old('email', $user?->email) }}"
                            class="w-full rounded border border-gray-300 px-3 py-2 text-sm"
                        >
                    </div>

                    <div>
                        <label for="password" class="mb-2 block text-sm font-medium text-gray-700">
                            {{ $editing ? 'Password Baru' : 'Password' }}
                        </label>
                        <input
                            id="password"
                            name="password"
                            type="password"
                            class="w-full rounded border border-gray-300 px-3 py-2 text-sm"
                        >
                        @if ($editing)
                            <p class="mt-2 text-xs text-gray-500">
                                Kosongkan apabila password tidak diubah.
                            </p>
                        @endif
                    </div>

                    <div>
                        <label for="password_confirmation" class="mb-2 block text-sm font-medium text-gray-700">
                            Konfirmasi Password
                        </label>
                        <input
                            id="password_confirmation"
                            name="password_confirmation"
                            type="password"
                            class="w-full rounded border border-gray-300 px-3 py-2 text-sm"
                        >
                    </div>
                </div>
            </section>

            <section class="rounded-lg bg-white p-6 shadow">
                <h2 class="text-base font-semibold text-gray-900">Hak Akses</h2>
                <p class="mt-1 text-sm text-gray-600">
                    Pilih minimal satu modul. Operator akan diarahkan ke modul pertama yang dapat diakses setelah login.
                </p>

                <div class="mt-5 grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
                    @foreach ($permissions as $permission)
                        <label class="flex cursor-pointer items-start gap-3 rounded border border-gray-200 p-4 hover:bg-gray-50">
                            <input
                                type="checkbox"
                                name="permissions[]"
                                value="{{ $permission->id }}"
                                @checked(in_array($permission->id, array_map('intval', $selectedPermissions), true))
                                class="mt-1"
                            >
                            <span>
                                <span class="block text-sm font-semibold text-gray-900">
                                    {{ $permission->name }}
                                </span>
                                <span class="mt-1 block text-xs text-gray-500">
                                    {{ $permission->code }}
                                </span>
                            </span>
                        </label>
                    @endforeach
                </div>
            </section>

            <div class="flex justify-end gap-3">
                <a
                    href="{{ route('admin.users.index') }}"
                    class="rounded border border-gray-300 bg-white px-4 py-2 text-sm font-semibold text-gray-700 hover:bg-gray-50"
                >
                    Batal
                </a>
                <button
                    type="submit"
                    class="rounded bg-blue-600 px-4 py-2 text-sm font-semibold text-white hover:bg-blue-700"
                >
                    {{ $editing ? 'Simpan Perubahan' : 'Tambah Operator' }}
                </button>
            </div>
        </form>
    </div>
</x-layouts.app>
