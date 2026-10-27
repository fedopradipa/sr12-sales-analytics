<x-layouts.app title="Kelola Operator">
    <div class="sr12-page space-y-6">
        @foreach (['success' => 'green', 'error' => 'red'] as $key => $color)
            @if (session($key))
                <div
                    class="rounded border border-{{ $color }}-200 bg-{{ $color }}-50 px-4 py-3 text-sm text-{{ $color }}-800"
                    role="alert"
                >
                    {{ session($key) }}
                </div>
            @endif
        @endforeach

        <div class="flex flex-wrap items-center justify-between gap-4">
            <div>
                <h1 class="text-xl font-semibold text-gray-900">
                    Kelola Operator
                </h1>
                <p class="mt-1 text-sm text-gray-600">
                    Tambah, ubah, hapus, dan atur hak akses operator.
                </p>
            </div>

            <a
                href="{{ route('admin.users.create') }}"
                class="rounded bg-blue-600 px-4 py-2 text-sm font-semibold text-white hover:bg-blue-700"
            >
                Tambah Operator
            </a>
        </div>

        <section class="overflow-hidden rounded-lg bg-white shadow">
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-200">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="px-5 py-3 text-left text-xs font-semibold uppercase text-gray-600">
                                Operator
                            </th>
                            <th class="px-5 py-3 text-left text-xs font-semibold uppercase text-gray-600">
                                Hak Akses
                            </th>
                            <th class="px-5 py-3 text-left text-xs font-semibold uppercase text-gray-600">
                                Dibuat
                            </th>
                            <th class="px-5 py-3 text-right text-xs font-semibold uppercase text-gray-600">
                                Aksi
                            </th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-200">
                        @forelse ($users as $operator)
                            <tr>
                                <td class="px-5 py-4">
                                    <p class="text-sm font-semibold text-gray-900">
                                        {{ $operator->name }}
                                    </p>
                                    <p class="mt-1 text-xs text-gray-500">
                                        {{ $operator->email }}
                                    </p>
                                </td>
                                <td class="px-5 py-4 text-sm text-gray-700">
                                    <p class="font-medium text-gray-900">
                                        {{ $operator->permissions->count() }} hak akses
                                    </p>
                                    <p class="mt-1 text-xs leading-5 text-gray-500">
                                        {{ $operator->permissions->pluck('name')->join(', ') }}
                                    </p>
                                </td>
                                <td class="whitespace-nowrap px-5 py-4 text-sm text-gray-700">
                                    {{ $operator->created_at?->format('d/m/Y') }}
                                </td>
                                <td class="px-5 py-4">
                                    <div class="flex justify-end gap-3">
                                        <a
                                            href="{{ route('admin.users.edit', $operator) }}"
                                            class="text-sm font-semibold text-blue-600 hover:text-blue-800"
                                        >
                                            Edit
                                        </a>

                                        <form
                                            action="{{ route('admin.users.destroy', $operator) }}"
                                            method="POST"
                                            data-confirm-submit="Hapus operator {{ $operator->name }}? Histori transaksi dan analisis tetap dipertahankan."
                                        >
                                            @csrf
                                            @method('DELETE')
                                            <button
                                                type="submit"
                                                class="text-sm font-semibold text-red-600 hover:text-red-800"
                                            >
                                                Hapus
                                            </button>
                                        </form>
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="4" class="px-6 py-10 text-center text-sm text-gray-500">
                                    Belum ada operator.
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            @if ($users->hasPages())
                <div class="border-t px-6 py-4">{{ $users->links() }}</div>
            @endif
        </section>
    </div>
</x-layouts.app>
