// @ts-check

/**
 * @typedef {'create' | 'edit'} FormModalMode
 */

/**
 * @typedef {Object} FormModalState
 * @property {FormModalMode} mode
 * @property {string} id
 * @property {string} action
 * @property {Record<string, string>} values
 * @property {HTMLElement|null} trigger
 */

/**
 * Mengisi input form berdasarkan nama field.
 *
 * @param {HTMLFormElement} form
 * @param {string} name
 * @param {string} value
 * @returns {void}
 */
const setFieldValue = (form, name, value) => {
    const control = form.elements.namedItem(name);

    if (control instanceof RadioNodeList) {
        for (const item of control) {
            if (
                item instanceof HTMLInputElement
                && ['radio', 'checkbox'].includes(item.type)
            ) {
                item.checked = item.value === value;
            }
        }

        return;
    }

    if (control instanceof HTMLInputElement) {
        if (control.type === 'checkbox') {
            control.checked = ['1', 'true', 'on'].includes(value);
            return;
        }

        if (control.type === 'radio') {
            control.checked = control.value === value;
            return;
        }

        control.value = value;
        return;
    }

    if (
        control instanceof HTMLSelectElement
        || control instanceof HTMLTextAreaElement
    ) {
        control.value = value;
    }
};

/**
 * Mengambil field dari atribut data-form-value-*.
 *
 * @param {HTMLElement} trigger
 * @returns {Record<string, string>}
 */
const readTriggerValues = (trigger) => {
    /** @type {Record<string, string>} */
    const values = {};

    for (const attribute of trigger.attributes) {
        const prefix = 'data-form-value-';

        if (!attribute.name.startsWith(prefix)) {
            continue;
        }

        const fieldName = attribute.name.slice(prefix.length);

        values[fieldName] = attribute.value;
    }

    return values;
};

/**
 * Mengambil nilai form untuk membuka ulang modal setelah validasi gagal.
 *
 * @param {HTMLFormElement} form
 * @returns {Record<string, string>}
 */
const readFormValues = (form) => {
    /** @type {Record<string, string>} */
    const values = {};

    const excludedFields = new Set([
        '_token',
        '_method',
        'modal_mode',
        'record_id',
        'form_context',
    ]);

    const formData = new FormData(form);

    for (const [name, value] of formData.entries()) {
        if (
            excludedFields.has(name)
            || typeof value !== 'string'
        ) {
            continue;
        }

        values[name] = value;
    }

    return values;
};

/**
 * Membersihkan pesan validasi lama saat modal dibuka manual.
 *
 * @param {HTMLFormElement} form
 * @returns {void}
 */
const clearValidationErrors = (form) => {
    form.querySelectorAll('[data-form-error]').forEach((element) => {
        if (element instanceof HTMLElement) {
            element.hidden = true;
        }
    });

    form.querySelectorAll('[aria-invalid="true"]').forEach((element) => {
        element.setAttribute('aria-invalid', 'false');
        element.removeAttribute('aria-describedby');
    });
};

/**
 * Mengembalikan field yang harus menerima fokus awal.
 *
 * @param {HTMLFormElement} form
 * @returns {HTMLElement|null}
 */
const getFocusTarget = (form) => {
    const focusField = form.dataset.focusField;

    if (focusField) {
        const control = form.elements.namedItem(focusField);

        if (control instanceof HTMLElement) {
            return control;
        }
    }

    return form.querySelector(
        'input:not([type="hidden"]):not([disabled]), '
        + 'select:not([disabled]), '
        + 'textarea:not([disabled]), '
        + 'button:not([disabled])'
    );
};

/**
 * Menginisialisasi seluruh modal form dalam halaman.
 *
 * @param {Document|HTMLElement} root
 * @returns {void}
 */
export const initializeFormModals = (root = document) => {
    /** @type {Map<string, {
     *     open: (
     *         state: FormModalState,
     *         preserveErrors?: boolean
     *     ) => void
     * }>} */
    const controllers = new Map();

    const dialogs = root.querySelectorAll(
        'dialog[data-form-modal]'
    );

    dialogs.forEach((element) => {
        if (!(element instanceof HTMLDialogElement)) {
            return;
        }

        const dialog = element;
        const form = dialog.querySelector(
            '[data-form-modal-form]'
        );
        const title = dialog.querySelector(
            '[data-modal-title]'
        );

        if (
            !(form instanceof HTMLFormElement)
            || !(title instanceof HTMLElement)
        ) {
            throw new Error(
                `Struktur modal ${dialog.id} tidak valid.`
            );
        }

        const methodInput = form.querySelector(
            '[data-form-method]'
        );
        const modeInput = form.querySelector(
            '[data-form-mode]'
        );
        const recordIdInput = form.querySelector(
            '[data-form-record-id]'
        );
        const submitButton = form.querySelector(
            '[data-form-submit]'
        );

        if (
            !(methodInput instanceof HTMLInputElement)
            || !(modeInput instanceof HTMLInputElement)
            || !(recordIdInput instanceof HTMLInputElement)
            || !(submitButton instanceof HTMLButtonElement)
        ) {
            throw new Error(
                `Elemen internal modal ${dialog.id} tidak lengkap.`
            );
        }

        /** @type {FormModalState} */
        let state = {
            mode: 'create',
            id: '',
            action: form.dataset.storeUrl ?? form.action,
            values: {},
            trigger: null,
        };

        /**
         * @param {FormModalState} nextState
         * @param {boolean} preserveErrors
         * @returns {void}
         */
        const render = (
            nextState,
            preserveErrors
        ) => {
            state = nextState;

            form.reset();

            if (!preserveErrors) {
                clearValidationErrors(form);
            }

            const isEdit = state.mode === 'edit';

            form.action =
                state.action
                || form.dataset.storeUrl
                || form.action;

            methodInput.disabled = !isEdit;
            modeInput.value = state.mode;
            recordIdInput.value = state.id;

            for (const [name, value] of Object.entries(state.values)) {
                setFieldValue(form, name, value);
            }

            title.textContent = isEdit
                ? form.dataset.editTitle ?? 'Edit Data'
                : form.dataset.createTitle ?? 'Tambah Data';

            submitButton.textContent = isEdit
                ? form.dataset.editSubmitLabel ?? 'Simpan Perubahan'
                : form.dataset.createSubmitLabel ?? 'Simpan';

            submitButton.disabled = false;
        };

        /**
         * @param {FormModalState} nextState
         * @param {boolean} preserveErrors
         * @returns {void}
         */
        const open = (
            nextState,
            preserveErrors = false
        ) => {
            render(nextState, preserveErrors);

            if (!dialog.open) {
                dialog.showModal();
            }

            window.requestAnimationFrame(() => {
                const focusTarget = getFocusTarget(form);

                focusTarget?.focus();

                if (focusTarget instanceof HTMLInputElement) {
                    focusTarget.select();
                }
            });
        };

        dialog.addEventListener('click', (event) => {
            const target = event.target;

            if (target === dialog) {
                dialog.close();
                return;
            }

            if (
                target instanceof Element
                && target.closest('[data-modal-close]')
            ) {
                dialog.close();
            }
        });

        dialog.addEventListener('close', () => {
            state.trigger?.focus();
        });

        form.addEventListener('submit', () => {
            submitButton.disabled = true;
            submitButton.textContent = 'Menyimpan...';
        });

        controllers.set(dialog.id, { open });

        if (form.dataset.openOnLoad === 'true') {
            const oldMode =
                form.dataset.oldMode === 'edit'
                && form.dataset.oldId
                    ? 'edit'
                    : 'create';

            open(
                {
                    mode: oldMode,
                    id: form.dataset.oldId ?? '',
                    action: form.action,
                    values: readFormValues(form),
                    trigger: null,
                },
                true
            );
        }
    });

    root.addEventListener('click', (event) => {
        const target = event.target;

        if (!(target instanceof Element)) {
            return;
        }

        const trigger = target.closest(
            '[data-form-modal-open]'
        );

        if (!(trigger instanceof HTMLElement)) {
            return;
        }

        const modalId = trigger.dataset.formModalOpen;

        if (!modalId) {
            return;
        }

        const controller = controllers.get(modalId);

        if (!controller) {
            throw new Error(
                `Modal ${modalId} tidak ditemukan.`
            );
        }

        controller.open({
            mode:
                trigger.dataset.formMode === 'edit'
                    ? 'edit'
                    : 'create',
            id: trigger.dataset.formRecordId ?? '',
            action: trigger.dataset.formAction ?? '',
            values: readTriggerValues(trigger),
            trigger,
        });
    });
};

initializeFormModals();