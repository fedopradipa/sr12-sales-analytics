@php
    $stockOldRecordId = (string) old(
        'stock_record_id',
        ''
    );

    $shouldOpenStockModal =
        old('form_context') === 'produk_stok'
        && $stockOldRecordId !== ''
        && $errors->any();

    $stockAction = $shouldOpenStockModal
        ? route(
            'admin.produk.stok.update',
            $stockOldRecordId
        )
        : route('admin.produk.index');

    $stockErrors = $shouldOpenStockModal
        ? $errors->get('stok')
        : [];
@endphp

<x-ui.modal
    id="produk-stock-modal"
    title="Perbarui Stok Produk"
    maxWidth="max-w-lg"
    data-stock-modal
    data-stock-open-on-load="{{ $shouldOpenStockModal ? 'true' : 'false' }}"
>
    <form
        method="POST"
        action="{{ $stockAction }}"
        class="space-y-5"
        data-stock-form
        data-submit-once
    >
        @csrf
        @method('PUT')

        <input
            type="hidden"
            name="form_context"
            value="produk_stok"
        >

        <input
            type="hidden"
            name="stock_record_id"
            value="{{ old('stock_record_id') }}"
            data-stock-record-id
        >

        <input
            type="hidden"
            name="stock_label"
            value="{{ old('stock_label') }}"
            data-stock-label-input
        >

        <input
            type="hidden"
            name="stok_lama"
            value="{{ old('stok_lama') }}"
            data-stock-old-input
        >

        <div
            class="rounded border border-gray-200 bg-gray-50 px-4 py-3"
        >
            <span
                class="block text-xs font-medium uppercase tracking-wide text-gray-500"
            >
                Produk
            </span>

            <strong
                class="mt-1 block text-sm text-gray-800"
                data-stock-product-label
            >
                {{ old('stock_label', '-') }}
            </strong>
        </div>

        <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
            <div>
                <label
                    for="produk-stok-lama"
                    class="mb-1 block text-sm font-medium text-gray-700"
                >
                    Stok Saat Ini
                </label>

                <input
                    id="produk-stok-lama"
                    type="number"
                    value="{{ old('stok_lama') }}"
                    disabled
                    data-stock-current
                    class="w-full cursor-not-allowed rounded border border-gray-200 bg-gray-100 px-3 py-2 text-sm text-gray-600"
                >
            </div>

            <div>
                <label
                    for="produk-stok-baru"
                    class="mb-1 block text-sm font-medium text-gray-700"
                >
                    Stok Aktual Baru
                </label>

                <input
                    id="produk-stok-baru"
                    type="number"
                    name="stok"
                    value="{{ old('stok') }}"
                    min="0"
                    step="1"
                    required
                    aria-invalid="{{ $stockErrors ? 'true' : 'false' }}"
                    @if ($stockErrors)
                        aria-describedby="produk-stok-error"
                    @endif
                    data-stock-new
                    class="w-full rounded border border-gray-300 px-3 py-2 text-sm focus:border-blue-500 focus:outline-none focus:ring-1 focus:ring-blue-500"
                >

                <x-forms.input-error
                    id="produk-stok-error"
                    :messages="$stockErrors"
                />
            </div>
        </div>

        <div
            class="rounded border border-gray-200 bg-gray-50 px-4 py-3 text-sm text-gray-600"
        >
            Waktu pembaruan stok akan dicatat otomatis
            oleh sistem.
        </div>

        <div class="flex justify-end gap-2">
            <x-ui.button
                type="button"
                variant="secondary"
                data-modal-close
            >
                Batal
            </x-ui.button>

            <x-ui.button>
                Simpan Stok
            </x-ui.button>
        </div>
    </form>
</x-ui.modal>

<script type="module">
{
    const dialog = document.getElementById(
        'produk-stock-modal'
    );

    if (dialog) {
        const form = dialog.querySelector(
            '[data-stock-form]'
        );

        const label = dialog.querySelector(
            '[data-stock-product-label]'
        );

        const currentInput = dialog.querySelector(
            '[data-stock-current]'
        );

        const newInput = dialog.querySelector(
            '[data-stock-new]'
        );

        const recordInput = dialog.querySelector(
            '[data-stock-record-id]'
        );

        const labelInput = dialog.querySelector(
            '[data-stock-label-input]'
        );

        const oldInput = dialog.querySelector(
            '[data-stock-old-input]'
        );

        const openModal = (button) => {
            const current =
                button.dataset.stockCurrent ?? '0';

            const productLabel =
                button.dataset.stockLabel ?? '';

            form.action =
                button.dataset.stockAction
                ?? form.action;

            label.textContent = productLabel;

            currentInput.value = current;
            newInput.value = current;

            recordInput.value =
                button.dataset.stockRecordId ?? '';

            labelInput.value = productLabel;
            oldInput.value = current;

            if (! dialog.open) {
                dialog.showModal();
            }

            newInput.focus();
            newInput.select();
        };

        document
            .querySelectorAll('[data-stock-modal-open]')
            .forEach((button) => {
                button.addEventListener(
                    'click',
                    () => openModal(button)
                );
            });

        dialog
            .querySelectorAll('[data-modal-close]')
            .forEach((button) => {
                button.addEventListener(
                    'click',
                    () => dialog.close()
                );
            });

        dialog.addEventListener(
            'click',
            (event) => {
                if (event.target === dialog) {
                    dialog.close();
                }
            }
        );

        if (
            dialog.dataset.stockOpenOnLoad === 'true'
            && ! dialog.open
        ) {
            dialog.showModal();
            newInput.focus();
        }
    }
}
</script>
