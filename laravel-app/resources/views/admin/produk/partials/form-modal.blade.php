@php
    $oldMode = old('modal_mode', 'create');
    $oldRecordId = (string) old('record_id', '');

    $isOldEdit =
        $oldMode === 'edit'
        && $oldRecordId !== '';

    $shouldOpenModal =
        old('form_context') === 'produk'
        && $errors->any();

    $formAction = $isOldEdit
        ? route('admin.produk.update', $oldRecordId)
        : route('admin.produk.store');

@endphp

<x-ui.modal
    id="produk-form-modal"
    title="Tambah Produk"
    maxWidth="max-w-2xl"
    data-form-modal
>
    <form
        method="POST"
        action="{{ $formAction }}"
        class="space-y-5"
        data-form-modal-form
        data-store-url="{{ route('admin.produk.store') }}"
        data-create-title="Tambah Produk"
        data-edit-title="Edit Produk"
        data-create-submit-label="Simpan"
        data-edit-submit-label="Simpan Perubahan"
        data-focus-field="kode"
        data-open-on-load="{{ $shouldOpenModal ? 'true' : 'false' }}"
        data-old-mode="{{ $isOldEdit ? 'edit' : 'create' }}"
        data-old-id="{{ $oldRecordId }}"
    >
        @csrf

        <input
            type="hidden"
            name="_method"
            value="PUT"
            @disabled(! $isOldEdit)
            data-form-method
        >

        <input
            type="hidden"
            name="form_context"
            value="produk"
        >

        <input
            type="hidden"
            name="modal_mode"
            value="{{ $isOldEdit ? 'edit' : 'create' }}"
            data-form-mode
        >

        <input
            type="hidden"
            name="record_id"
            value="{{ $oldRecordId }}"
            data-form-record-id
        >

        <div>
            <x-forms.input
                label="Kode Produk"
                name="kode"
                :value="old('kode')"
                placeholder="Contoh: SR082"
                maxlength="20"
                autocomplete="off"
                required
            />

            <p
                data-product-code-lock-note
                hidden
                class="mt-1 text-xs leading-5 text-gray-500"
            >
                Kode produk tidak dapat diubah karena
                sudah digunakan dalam transaksi.
            </p>
        </div>

        <x-forms.input
            label="Nama Produk"
            name="nama"
            :value="old('nama')"
            placeholder="Contoh: Gomilku 600g"
            maxlength="60"
            autocomplete="off"
            required
        />

        <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
            <div data-product-stock-field>
                <x-forms.input
                    label="Stok Awal"
                    name="stok"
                    type="number"
                    :value="old('stok', 0)"
                    min="0"
                    step="1"
                    required
                />

                <p class="mt-1 text-xs leading-5 text-gray-500">
                    Diisi saat produk pertama kali
                    didaftarkan.
                </p>
            </div>

            <div>
                <x-forms.input
                    label="Stok Minimum"
                    name="stok_min"
                    type="number"
                    :value="old('stok_min', 0)"
                    min="0"
                    step="1"
                    required
                />

                <p class="mt-1 text-xs leading-5 text-gray-500">
                    Batas minimum persediaan produk.
                </p>
            </div>
        </div>

        <div
            data-product-edit-note
            hidden
            class="rounded border border-gray-200 bg-gray-50 px-4 py-3 text-sm text-gray-600"
        >
            Stok saat ini tidak diubah melalui form edit
            produk. Gunakan aksi
            <strong>Perbarui Stok</strong>.
        </div>

        <div class="flex justify-end gap-2">
            <x-ui.button
                type="button"
                variant="secondary"
                data-modal-close
            >
                Batal
            </x-ui.button>

            <x-ui.button data-form-submit>
                Simpan
            </x-ui.button>
        </div>
    </form>
</x-ui.modal>

<script type="module">
{
    const modal = document.getElementById(
        'produk-form-modal'
    );

    if (modal) {
        const form = modal.querySelector(
            '[data-form-modal-form]'
        );

        const stockField = modal.querySelector(
            '[data-product-stock-field]'
        );

        const stockInput = modal.querySelector(
            '[name="stok"]'
        );

        const codeInput = modal.querySelector(
            '[name="kode"]'
        );

        const codeLockNote = modal.querySelector(
            '[data-product-code-lock-note]'
        );

        const editNote = modal.querySelector(
            '[data-product-edit-note]'
        );

        const applyMode = (
            mode,
            codeLocked = false
        ) => {
            const editing = mode === 'edit';
            const lockCode =
                editing && codeLocked;

            if (stockField) {
                stockField.hidden = editing;
            }

            if (stockInput) {
                stockInput.disabled = editing;
                stockInput.required = ! editing;
            }

            if (codeInput) {
                codeInput.readOnly = lockCode;

                codeInput.classList.toggle(
                    'bg-gray-100',
                    lockCode
                );

                codeInput.classList.toggle(
                    'cursor-not-allowed',
                    lockCode
                );
            }

            if (codeLockNote) {
                codeLockNote.hidden = ! lockCode;
            }

            if (editNote) {
                editNote.hidden = ! editing;
            }
        };

        document
            .querySelectorAll(
                '[data-form-modal-open="produk-form-modal"]'
            )
            .forEach((button) => {
                button.addEventListener(
                    'click',
                    () => {
                        applyMode(
                            button.dataset.formMode
                                ?? 'create',
                            button.dataset
                                .productCodeLocked
                                === '1'
                        );
                    }
                );
            });

        applyMode(
            form?.dataset.oldMode ?? 'create'
        );
    }
}
</script>
