<x-layouts.app title="Transaksi Penjualan">
    <section class="sr12-page space-y-6">
       <header class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
    <div>
        <h1 class="text-2xl font-bold text-gray-800">
            Transaksi Penjualan
        </h1>

        <p class="mt-1 text-sm text-gray-500">
            Lihat detail transaksi yang digunakan untuk segmentasi dan peramalan penjualan.
        </p>
    </div>

    <div class="flex flex-wrap items-center gap-2">
        <a
            href="{{ route('admin.transaksi.import.create') }}"
            class="rounded bg-blue-600 px-4 py-2 text-center text-sm font-semibold text-white transition hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:ring-offset-2"
        >
            Import Transaksi
        </a>

        <form
            method="POST"
            action="{{ route('admin.transaksi.destroy-all') }}"
            onsubmit="return confirm('Hapus seluruh data transaksi penjualan? Tindakan ini tidak dapat dibatalkan.');"
        >
            @csrf
            @method('DELETE')

            <button
                type="submit"
                class="rounded bg-red-600 px-4 py-2 text-sm font-semibold text-white transition hover:bg-red-700 disabled:cursor-not-allowed disabled:opacity-50"
            >
                Hapus Semua
            </button>
        </form>
    </div>
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
            action="{{ route('admin.transaksi.index') }}"
            class="grid grid-cols-1 gap-4 rounded bg-white p-4 shadow md:grid-cols-6"
            aria-label="Filter transaksi penjualan"
        >
            <div class="md:col-span-2">
                <label
                    for="filter-transaksi-search"
                    class="mb-1 block text-sm font-medium text-gray-700"
                >
                    Pencarian
                </label>

                <input
                    id="filter-transaksi-search"
                    type="search"
                    name="search"
                    value="{{ request('search') }}"
                    maxlength="120"
                    placeholder="Nomor faktur, kode, atau nama produk"
                    class="w-full rounded border border-gray-300 px-3 py-2 text-sm focus:border-blue-500 focus:outline-none focus:ring-1 focus:ring-blue-500"
                >
            </div>

            <div>
                <label
                    for="filter-tanggal-awal"
                    class="mb-1 block text-sm font-medium text-gray-700"
                >
                    Tanggal Awal
                </label>

                <input
                    id="filter-tanggal-awal"
                    type="date"
                    name="tanggal_awal"
                    value="{{ request('tanggal_awal') }}"
                    class="w-full rounded border border-gray-300 px-3 py-2 text-sm focus:border-blue-500 focus:outline-none focus:ring-1 focus:ring-blue-500"
                >
            </div>

            <div>
                <label
                    for="filter-tanggal-akhir"
                    class="mb-1 block text-sm font-medium text-gray-700"
                >
                    Tanggal Akhir
                </label>

                <input
                    id="filter-tanggal-akhir"
                    type="date"
                    name="tanggal_akhir"
                    value="{{ request('tanggal_akhir') }}"
                    class="w-full rounded border border-gray-300 px-3 py-2 text-sm focus:border-blue-500 focus:outline-none focus:ring-1 focus:ring-blue-500"
                >
            </div>

            <div class="md:col-span-2">
                <label
                    for="filter-transaksi-produk"
                    class="mb-1 block text-sm font-medium text-gray-700"
                >
                    Produk
                </label>

                <select
                    id="filter-transaksi-produk"
                    name="produk_id"
                    class="w-full rounded border border-gray-300 px-3 py-2 text-sm focus:border-blue-500 focus:outline-none focus:ring-1 focus:ring-blue-500"
                >
                    <option value="">
                        Semua produk
                    </option>

                    @foreach ($daftarProduk as $produk)
                        <option
                            value="{{ $produk->id }}"
                            @selected(
                                (string) request('produk_id')
                                === (string) $produk->id
                            )
                        >
                            {{ $produk->kode }} — {{ $produk->nama }}
                            {{ $produk->trashed() ? '(Dihapus)' : '' }}
                        </option>
                    @endforeach
                </select>
            </div>

            <div class="md:col-span-3">
                <x-ui.button full>
                    Terapkan Filter
                </x-ui.button>
            </div>

            <div class="md:col-span-3">
                <a
                    href="{{ route('admin.transaksi.index') }}"
                    class="block w-full rounded bg-gray-200 px-4 py-2 text-center text-sm font-semibold text-gray-700 transition hover:bg-gray-300 focus:outline-none focus:ring-2 focus:ring-gray-400 focus:ring-offset-2"
                >
                    Reset
                </a>
            </div>
        </form>

        <div class="overflow-x-auto rounded bg-white shadow">
            <table class="min-w-full divide-y divide-gray-200">
                    <thead class="bg-gray-50">
                        <tr>
                            <th
                                scope="col"
                                class="px-5 py-3 text-left text-xs font-semibold uppercase text-gray-600"
                            >
                                No
                            </th>

                            <th
                                scope="col"
                                class="px-5 py-3 text-left text-xs font-semibold uppercase text-gray-600"
                            >
                                No Faktur
                            </th>

                            <th
                                scope="col"
                                class="px-5 py-3 text-left text-xs font-semibold uppercase text-gray-600"
                            >
                                Tanggal
                            </th>

                            <th
                                scope="col"
                                class="px-5 py-3 text-left text-xs font-semibold uppercase text-gray-600"
                            >
                                Kode Produk
                            </th>

                            <th
                                scope="col"
                                class="px-5 py-3 text-left text-xs font-semibold uppercase text-gray-600"
                            >
                                Nama Produk
                            </th>

                            <th
                                scope="col"
                                class="px-5 py-3 text-right text-xs font-semibold uppercase text-gray-600"
                            >
                                Harga Jual
                            </th>

                            <th
                                scope="col"
                                class="px-5 py-3 text-center text-xs font-semibold uppercase text-gray-600"
                            >
                                Qty
                            </th>

                            <th
                                scope="col"
                                class="px-5 py-3 text-right text-xs font-semibold uppercase text-gray-600"
                            >
                                Nilai Penjualan
                            </th>

                            <th
                                scope="col"
                                class="px-5 py-3 text-right text-xs font-semibold uppercase text-gray-600"
                            >
                                Aksi
                            </th>
                        </tr>
                    </thead>

                    <tbody class="divide-y divide-gray-200">
                        @forelse ($daftarTransaksi as $transaksi)
                            <tr>
                                <td class="whitespace-nowrap px-5 py-4 text-sm text-gray-600">
                                    {{
                                        ($daftarTransaksi->firstItem() ?? 1)
                                        + $loop->index
                                    }}
                                </td>

                                <td class="whitespace-nowrap px-5 py-4 text-sm font-semibold text-gray-900">
                                    {{ $transaksi->faktur }}
                                </td>

                                <td class="whitespace-nowrap px-5 py-4 text-sm text-gray-700">
                                    {{ $transaksi->tanggal->format('d/m/Y') }}
                                </td>

                                <td class="whitespace-nowrap px-5 py-4 text-sm font-medium text-gray-800">
                                    {{ $transaksi->produk?->kode ?? '-' }}
                                </td>

                                <td class="min-w-64 px-5 py-4 text-sm text-gray-800">
                                    <div class="font-medium text-gray-900">
                                        {{
                                            $transaksi->produk?->nama
                                            ?? 'Produk tidak ditemukan'
                                        }}
                                    </div>

                                    @if ($transaksi->produk?->trashed())
                                        <span class="mt-1 inline-flex rounded-full bg-red-50 px-2 py-0.5 text-xs font-medium text-red-700">
                                            Produk dihapus
                                        </span>
                                    @endif
                                </td>

                                <td class="whitespace-nowrap px-5 py-4 text-right text-sm text-gray-700">
                                    Rp
                                    {{
                                        number_format(
                                            (int) $transaksi->harga,
                                            0,
                                            ',',
                                            '.'
                                        )
                                    }}
                                </td>

                                <td class="whitespace-nowrap px-5 py-4 text-center text-sm font-semibold text-gray-800">
                                    {{
                                        number_format(
                                            (int) $transaksi->qty,
                                            0,
                                            ',',
                                            '.'
                                        )
                                    }}
                                </td>

                                <td class="whitespace-nowrap px-5 py-4 text-right text-sm font-semibold text-gray-900">
                                    Rp
                                    {{
                                        number_format(
                                            (int) $transaksi->subtotal,
                                            0,
                                            ',',
                                            '.'
                                        )
                                    }}
                                </td>

                                <td
                                    class="whitespace-nowrap px-5 py-4"
                                >
                                    <div
                                        class="flex justify-end gap-2"
                                    >
                                            <button
                                                type="button"
                                                class="rounded border border-gray-300 bg-white px-3 py-1.5 text-xs font-semibold text-gray-700 transition hover:bg-gray-50"
                                                data-transaction-edit-open
                                                data-id="{{ $transaksi->id }}"
                                                data-update-url="{{ route(
                                                    'admin.transaksi.update',
                                                    $transaksi
                                                ) }}"
                                                data-tanggal="{{ $transaksi->tanggal->format('Y-m-d') }}"
                                                data-faktur="{{ $transaksi->faktur }}"
                                                data-produk-id="{{ $transaksi->produk_id }}"
                                                data-qty="{{ $transaksi->qty }}"
                                                data-harga="{{ $transaksi->harga }}"
                                            >
                                                Edit
                                            </button>

                                            <form
                                                method="POST"
                                                action="{{ route(
                                                    'admin.transaksi.destroy',
                                                    $transaksi
                                                ) }}"
                                                onsubmit="return confirm('Hapus transaksi {{ $transaksi->faktur }}?');"
                                            >
                                                @csrf
                                                @method('DELETE')

                                                <button
                                                    type="submit"
                                                    class="rounded bg-red-600 px-3 py-1.5 text-xs font-semibold text-white transition hover:bg-red-700"
                                                >
                                                    Hapus
                                                </button>
                                            </form>
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td
                                    colspan="9"
                                    class="px-6 py-12 text-center text-sm text-gray-500"
                                >
                                    Data transaksi tidak ditemukan.
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>

            @if ($daftarTransaksi->hasPages())
                <div class="border-t p-4">
                    {{ $daftarTransaksi->links() }}
                </div>
            @endif
        </div>
        @include(
            'admin.detail-transaksi.partials.edit-modal'
        )
    </section>
</x-layouts.app>
