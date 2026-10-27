<x-layouts.app title="Produk">
    <section class="sr12-page space-y-6">
        <header class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
            <div>
                <h1 class="text-2xl font-bold text-gray-800">
                    Produk
                </h1>

                <p class="mt-1 text-sm text-gray-500">
                    Kelola identitas produk, stok saat ini, dan stok minimum sebagai batas persediaan untuk menentukan stok yang perlu ditambah.
                </p>
            </div>

                <x-ui.button
                    type="button"
                    data-form-modal-open="produk-form-modal"
                    data-form-mode="create"
                    data-form-action="{{ route('admin.produk.store') }}"
                    data-form-value-kode=""
                    data-form-value-nama=""
                    data-form-value-stok="0"
                    data-form-value-stok_min="0"
                >
                    Tambah Produk
                </x-ui.button>
        </header>


        @if (session('success'))
            <div
                class="rounded bg-green-100 px-4 py-3 text-sm text-green-700"
                role="status"
                aria-live="polite"
            >
                {{ session('success') }}
            </div>
        @endif

        @if (session('error'))
            <div
                class="rounded bg-red-100 px-4 py-3 text-sm text-red-700"
                role="alert"
                aria-live="assertive"
            >
                {{ session('error') }}
            </div>
        @endif

        <form
            method="GET"
            action="{{ route('admin.produk.index') }}"
            class="grid grid-cols-1 gap-4 rounded bg-white p-4 shadow md:grid-cols-3"
            aria-label="Filter produk"
        >
            <div>
                <label
                    for="filter-produk-search"
                    class="mb-1 block text-sm font-medium text-gray-700"
                >
                    Pencarian
                </label>

                <input
                    id="filter-produk-search"
                    type="search"
                    name="search"
                    value="{{ request('search') }}"
                    maxlength="60"
                    placeholder="Cari kode atau nama produk"
                    class="w-full rounded border border-gray-300 px-3 py-2 text-sm focus:border-blue-500 focus:outline-none focus:ring-1 focus:ring-blue-500"
                >
            </div>


            <div class="flex items-end">
                <x-ui.button full>
                    Filter
                </x-ui.button>
            </div>

            <div class="flex items-end">
                <a
                    href="{{ route('admin.produk.index') }}"
                    class="w-full rounded bg-gray-200 px-4 py-2 text-center text-sm font-semibold text-gray-700 transition hover:bg-gray-300 focus:outline-none focus:ring-2 focus:ring-gray-400 focus:ring-offset-2"
                >
                    Reset
                </a>
            </div>
        </form>

        <div class="overflow-x-auto rounded bg-white shadow">
            <table class="w-full min-w-[1040px] border-collapse text-left text-sm">
                <thead class="bg-gray-50 text-gray-700">
                    <tr>
                        <th
                            scope="col"
                            class="w-20 border-b px-4 py-3"
                        >
                            No
                        </th>

                        <th
                            scope="col"
                            class="w-40 border-b px-4 py-3"
                        >
                            Kode
                        </th>

                        <th
                            scope="col"
                            class="border-b px-4 py-3"
                        >
                            Nama Produk
                        </th>
<th
                            scope="col"
                            class="w-28 border-b px-4 py-3 text-right"
                        >
                            Stok Saat Ini
                        </th>

                        <th
                            scope="col"
                            class="w-28 border-b px-4 py-3 text-right"
                        >
                            Stok Minimum
                        </th>

                        <th
                            scope="col"
                            class="w-44 border-b px-4 py-3"
                        >
                            Pembaruan Stok
                        </th>

                        <th
                            scope="col"
                            class="w-72 border-b px-4 py-3 text-right"
                        >
                            Aksi
                        </th>
                    </tr>
                </thead>

                <tbody>
                    @forelse ($daftarProduk as $index => $produk)
                        <tr class="hover:bg-gray-50">
                            <td class="border-b px-4 py-3 text-gray-600">
                                {{ $daftarProduk->firstItem() + $index }}
                            </td>

                            <td class="border-b px-4 py-3 font-medium text-gray-800">
                                {{ $produk->kode }}
                            </td>

                            <td class="border-b px-4 py-3 text-gray-700">
                                {{ $produk->nama }}
                            </td>


                            <td
                                class="border-b px-4 py-3 text-right font-semibold text-gray-800"
                            >
                                {{ number_format(
                                    $produk->stok,
                                    0,
                                    ',',
                                    '.'
                                ) }}
                            </td>

                            <td
                                class="border-b px-4 py-3 text-right text-gray-700"
                            >
                                {{ number_format(
                                    $produk->stok_min,
                                    0,
                                    ',',
                                    '.'
                                ) }}
                            </td>

                            <td
                                class="border-b px-4 py-3 text-gray-600"
                            >
                                @if ($produk->stok_at)
                                    {{ $produk->stok_at
                                        ->format('d/m/Y H:i') }}
                                @else
                                    Belum diperbarui
                                @endif
                            </td>

                            <td class="border-b px-4 py-3">
                                <div class="flex flex-wrap justify-end gap-2">
                                    <x-ui.button
                                        type="button"
                                        variant="secondary"
                                        aria-label="Edit produk {{ $produk->nama }}"
                                        data-form-modal-open="produk-form-modal"
                                        data-form-mode="edit"
                                        data-form-record-id="{{ $produk->id }}"
                                        data-form-action="{{ route('admin.produk.update', $produk) }}"
                                        data-form-value-kode="{{ $produk->kode }}"
                                        data-product-code-locked="{{ $produk->punya_transaksi ? '1' : '0' }}"
                                        data-form-value-nama="{{ $produk->nama }}"
                                        data-form-value-stok="{{ $produk->stok }}"
                                        data-form-value-stok_min="{{ $produk->stok_min }}"
                                    >
                                        Edit
                                    </x-ui.button>

                                    <x-ui.button
                                        type="button"
                                        variant="secondary"
                                        aria-label="Perbarui stok {{ $produk->nama }}"
                                        data-stock-modal-open
                                        data-stock-action="{{ route('admin.produk.stok.update', $produk) }}"
                                        data-stock-record-id="{{ $produk->id }}"
                                        data-stock-label="{{ $produk->kode }} — {{ $produk->nama }}"
                                        data-stock-current="{{ $produk->stok }}"
                                    >
                                        Perbarui Stok
                                    </x-ui.button>

                                    <form
                                        action="{{ route('admin.produk.destroy', $produk) }}"
                                        method="POST"
                                        data-confirm-submit
                                        data-confirm-message="Yakin ingin menghapus produk {{ $produk->nama }}?"
                                    >
                                        @csrf
                                        @method('DELETE')

                                        <x-ui.button
                                            variant="danger"
                                            aria-label="Hapus produk {{ $produk->nama }}"
                                        >
                                            Hapus
                                        </x-ui.button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td
                                colspan="7"
                                class="px-4 py-8 text-center text-gray-500"
                            >
                                Data produk belum tersedia.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>

            @if ($daftarProduk->hasPages())
                <div class="border-t p-4">
                    {{ $daftarProduk->links() }}
                </div>
            @endif
        </div>

        @include('admin.produk.partials.form-modal')
        @include('admin.produk.partials.stock-modal')
    </section>
</x-layouts.app>
