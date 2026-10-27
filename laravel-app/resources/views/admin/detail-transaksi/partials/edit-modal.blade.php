@php
    $modalTransaksiId = old(
        'modal_transaksi_id'
    );

    $shouldOpenEditModal =
        $errors->any()
        && filled($modalTransaksiId);

    $modalAction = $shouldOpenEditModal
        ? route(
            'admin.transaksi.update',
            ['detailTransaksi' => $modalTransaksiId]
        )
        : '#';
@endphp

<dialog
    id="transaction-edit-modal"
    class="m-auto w-[calc(100%_-_2rem)] max-w-2xl rounded-2xl bg-transparent p-0 shadow-[0_35px_100px_rgba(77,36,43,.34)] backdrop:bg-[#382b31]/55 backdrop:backdrop-blur-sm"
    data-transaction-edit-modal
    data-open-on-load="{{ $shouldOpenEditModal ? 'true' : 'false' }}"
>
    <div class="overflow-hidden rounded-2xl border border-rose-100 bg-white">
        <header
            class="flex items-center justify-between gap-4 border-b border-rose-100 bg-gradient-to-r from-rose-50 to-white px-6 py-4"
        >
            <div>
                <h2 class="text-lg font-semibold text-gray-900">
                    Edit Transaksi
                </h2>
                <p class="mt-1 text-sm text-gray-600">
                    Perbarui data transaksi penjualan.
                </p>
            </div>

            <button
                type="button"
                class="rounded-lg p-2 text-gray-500 transition hover:bg-white hover:text-gray-800"
                aria-label="Tutup modal"
                data-transaction-edit-close
            >
                ✕
            </button>
        </header>

        <form
            method="POST"
            action="{{ $modalAction }}"
            class="space-y-5 p-6"
            data-transaction-edit-form
        >
            @csrf
            @method('PUT')

            <input
                type="hidden"
                name="modal_transaksi_id"
                value="{{ old('modal_transaksi_id') }}"
                data-edit-id
            >

            @if ($shouldOpenEditModal && $errors->any())
                <div
                    class="rounded-lg border border-red-300 bg-red-100 px-4 py-3 text-sm text-red-800"
                    role="alert"
                    data-transaction-modal-error
                >
                    Periksa kembali data transaksi yang ditandai.
                </div>
            @endif

            <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                <div>
                    <label
                        for="edit-transaksi-tanggal"
                        class="mb-1 block text-sm font-medium text-gray-700"
                    >
                        Tanggal
                    </label>
                    <input
                        id="edit-transaksi-tanggal"
                        type="date"
                        name="tanggal"
                        value="{{ old('tanggal') }}"
                        class="w-full rounded border border-gray-300 px-3 py-2 text-sm"
                        data-edit-tanggal
                    >
                    @error('tanggal')
                        <p class="mt-1 text-xs text-red-600" data-transaction-modal-error>
                            {{ $message }}
                        </p>
                    @enderror
                </div>

                <div>
                    <label
                        for="edit-transaksi-faktur"
                        class="mb-1 block text-sm font-medium text-gray-700"
                    >
                        No Faktur
                    </label>
                    <input
                        id="edit-transaksi-faktur"
                        type="text"
                        name="faktur"
                        maxlength="20"
                        value="{{ old('faktur') }}"
                        class="w-full rounded border border-gray-300 px-3 py-2 text-sm"
                        data-edit-faktur
                    >
                    @error('faktur')
                        <p class="mt-1 text-xs text-red-600" data-transaction-modal-error>
                            {{ $message }}
                        </p>
                    @enderror
                </div>
            </div>

            <div>
                <label
                    for="edit-transaksi-produk"
                    class="mb-1 block text-sm font-medium text-gray-700"
                >
                    Produk
                </label>
                <select
                    id="edit-transaksi-produk"
                    name="produk_id"
                    class="w-full rounded border border-gray-300 px-3 py-2 text-sm"
                    data-edit-produk
                >
                    @foreach ($daftarProduk as $produk)
                        <option
                            value="{{ $produk->id }}"
                            @selected(
                                (string) old('produk_id')
                                === (string) $produk->id
                            )
                        >
                            {{ $produk->kode }} — {{ $produk->nama }}
                            {{ $produk->trashed() ? '(Dihapus)' : '' }}
                        </option>
                    @endforeach
                </select>
                @error('produk_id')
                    <p class="mt-1 text-xs text-red-600" data-transaction-modal-error>
                        {{ $message }}
                    </p>
                @enderror
            </div>

            <div class="grid grid-cols-1 gap-4 sm:grid-cols-3">
                <div>
                    <label
                        for="edit-transaksi-qty"
                        class="mb-1 block text-sm font-medium text-gray-700"
                    >
                        Qty
                    </label>
                    <input
                        id="edit-transaksi-qty"
                        type="number"
                        name="qty"
                        min="1"
                        step="1"
                        value="{{ old('qty') }}"
                        class="w-full rounded border border-gray-300 px-3 py-2 text-sm"
                        data-edit-qty
                    >
                    @error('qty')
                        <p class="mt-1 text-xs text-red-600" data-transaction-modal-error>
                            {{ $message }}
                        </p>
                    @enderror
                </div>

                <div>
                    <label
                        for="edit-transaksi-harga"
                        class="mb-1 block text-sm font-medium text-gray-700"
                    >
                        Harga Jual
                    </label>
                    <input
                        id="edit-transaksi-harga"
                        type="number"
                        name="harga"
                        min="0"
                        step="1"
                        value="{{ old('harga') }}"
                        class="w-full rounded border border-gray-300 px-3 py-2 text-sm"
                        data-edit-harga
                    >
                    @error('harga')
                        <p class="mt-1 text-xs text-red-600" data-transaction-modal-error>
                            {{ $message }}
                        </p>
                    @enderror
                </div>

                <div>
                    <label
                        for="edit-transaksi-subtotal"
                        class="mb-1 block text-sm font-medium text-gray-700"
                    >
                        Nilai Penjualan
                    </label>
                    <input
                        id="edit-transaksi-subtotal"
                        type="number"
                        readonly
                        class="w-full cursor-not-allowed rounded border border-gray-200 bg-gray-100 px-3 py-2 text-sm text-gray-700"
                        data-edit-subtotal
                    >
                    <p class="mt-1 text-xs text-gray-500">
                        Dihitung otomatis dari Qty × Harga Jual.
                    </p>
                </div>
            </div>

            @error('transaksi')
                <div
                    class="rounded-lg border border-red-300 bg-red-100 px-4 py-3 text-sm text-red-800"
                    data-transaction-modal-error
                >
                    {{ $message }}
                </div>
            @enderror

            <div class="flex justify-end gap-2 border-t border-gray-200 pt-4">
                <x-ui.button
                    type="button"
                    variant="secondary"
                    data-transaction-edit-close
                >
                    Batal
                </x-ui.button>

                <x-ui.button>
                    Simpan Perubahan
                </x-ui.button>
            </div>
        </form>
    </div>
</dialog>
