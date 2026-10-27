const dialog = document.querySelector(
    '[data-transaction-edit-modal]',
);

if (dialog instanceof HTMLDialogElement) {
    const form = dialog.querySelector(
        '[data-transaction-edit-form]',
    );

    const fields = {
        id: dialog.querySelector('[data-edit-id]'),
        tanggal: dialog.querySelector('[data-edit-tanggal]'),
        faktur: dialog.querySelector('[data-edit-faktur]'),
        produk: dialog.querySelector('[data-edit-produk]'),
        qty: dialog.querySelector('[data-edit-qty]'),
        harga: dialog.querySelector('[data-edit-harga]'),
        subtotal: dialog.querySelector('[data-edit-subtotal]'),
    };

    const updateSubtotal = () => {
        if (!fields.subtotal) {
            return;
        }

        const qty = Number(fields.qty?.value ?? 0);
        const harga = Number(fields.harga?.value ?? 0);

        fields.subtotal.value = String(qty * harga);
    };

    const hideServerErrors = () => {
        dialog
            .querySelectorAll('[data-transaction-modal-error]')
            .forEach((element) => {
                element.hidden = true;
            });
    };

    const openFromTrigger = (trigger) => {
        if (!(form instanceof HTMLFormElement)) {
            return;
        }

        form.action = trigger.dataset.updateUrl ?? '#';

        if (fields.id) {
            fields.id.value = trigger.dataset.id ?? '';
        }

        if (fields.tanggal) {
            fields.tanggal.value = trigger.dataset.tanggal ?? '';
        }

        if (fields.faktur) {
            fields.faktur.value = trigger.dataset.faktur ?? '';
        }

        if (fields.produk) {
            fields.produk.value = trigger.dataset.produkId ?? '';
        }

        if (fields.qty) {
            fields.qty.value = trigger.dataset.qty ?? '';
        }

        if (fields.harga) {
            fields.harga.value = trigger.dataset.harga ?? '';
        }

        hideServerErrors();
        updateSubtotal();

        if (!dialog.open) {
            dialog.showModal();
        }

        fields.tanggal?.focus();
    };

    document
        .querySelectorAll('[data-transaction-edit-open]')
        .forEach((trigger) => {
            trigger.addEventListener(
                'click',
                () => openFromTrigger(trigger),
            );
        });

    dialog
        .querySelectorAll('[data-transaction-edit-close]')
        .forEach((button) => {
            button.addEventListener(
                'click',
                () => dialog.close(),
            );
        });

    dialog.addEventListener('click', (event) => {
        if (event.target === dialog) {
            dialog.close();
        }
    });

    fields.qty?.addEventListener('input', updateSubtotal);
    fields.harga?.addEventListener('input', updateSubtotal);

    if (dialog.dataset.openOnLoad === 'true') {
        updateSubtotal();

        if (!dialog.open) {
            dialog.showModal();
        }
    }
}
