// @ts-check

const forms = document.querySelectorAll(
    '[data-submit-once]'
);

/** @type {WeakMap<HTMLButtonElement, string>} */
const originalButtonContents = new WeakMap();

/** @type {WeakMap<HTMLFormElement, HTMLElement>} */
const submitOverlays = new WeakMap();

/**
 * @param {HTMLFormElement} form
 * @returns {HTMLButtonElement|null}
 */
const getSubmitButton = (form) => {
    const button = form.querySelector(
        'button[type="submit"]'
    );

    return button instanceof HTMLButtonElement
        ? button
        : null;
};

/**
 * Mengambil overlay satu kali dan menyimpan referensinya.
 *
 * @param {HTMLFormElement} form
 * @returns {HTMLElement|null}
 */
const getSubmitOverlay = (form) => {
    const savedOverlay =
        submitOverlays.get(form);

    if (savedOverlay instanceof HTMLElement) {
        return savedOverlay;
    }

    const overlay = form.querySelector(
        '[data-submit-overlay]'
    );

    if (!(overlay instanceof HTMLElement)) {
        return null;
    }

    submitOverlays.set(form, overlay);

    return overlay;
};

/**
 * @param {HTMLFormElement} form
 * @returns {void}
 */
const showLoadingState = (form) => {
    const submitButton = getSubmitButton(form);

    if (submitButton === null) {
        return;
    }

    if (!originalButtonContents.has(submitButton)) {
        originalButtonContents.set(
            submitButton,
            submitButton.innerHTML
        );
    }

    form.dataset.submitting = 'true';
    form.setAttribute('aria-busy', 'true');

    submitButton.disabled = true;
    submitButton.setAttribute(
        'aria-disabled',
        'true'
    );

    submitButton.classList.add(
        'inline-flex',
        'items-center',
        'justify-center',
        'gap-2'
    );

    const spinner = document.createElement('span');

    spinner.className =
        'inline-block h-4 w-4 shrink-0 animate-spin '
        + 'rounded-full border-2 border-current '
        + 'border-r-transparent';

    spinner.setAttribute(
        'aria-hidden',
        'true'
    );

    const label = document.createElement('span');

    label.textContent =
        form.dataset.submitOnceLabel
        ?? 'Memproses...';

    submitButton.replaceChildren(
        spinner,
        label
    );

    const fileInput = form.querySelector(
        'input[type="file"]'
    );

    const fileName = form.querySelector(
        '[data-submit-file-name]'
    );

    if (
        fileInput instanceof HTMLInputElement
        && fileName instanceof HTMLElement
    ) {
        const selectedFile =
            fileInput.files?.[0];

        fileName.textContent = selectedFile
            ? selectedFile.name
            : 'File transaksi sedang diproses.';
    }

    const overlay = getSubmitOverlay(form);

    if (overlay instanceof HTMLElement) {
        /*
         * Pindahkan overlay langsung ke body agar benar-benar
         * menggunakan viewport browser dan tidak terikat oleh
         * container form atau layout.
         */
        if (overlay.parentElement !== document.body) {
            document.body.appendChild(overlay);
        }

        overlay.classList.add('is-visible');
        overlay.setAttribute(
            'aria-hidden',
            'false'
        );

        document.body.classList.add(
            'sr12-submit-locked'
        );
    }
};

/**
 * Mengembalikan state ketika halaman dipulihkan dari
 * browser back-forward cache.
 *
 * @param {HTMLFormElement} form
 * @returns {void}
 */
const resetLoadingState = (form) => {
    const submitButton = getSubmitButton(form);

    form.removeAttribute('data-submitting');
    form.removeAttribute('aria-busy');

    if (submitButton !== null) {
        submitButton.disabled = false;

        submitButton.removeAttribute(
            'aria-disabled'
        );

        const originalContent =
            originalButtonContents.get(
                submitButton
            );

        if (originalContent !== undefined) {
            submitButton.innerHTML =
                originalContent;
        }
    }

    const overlay = getSubmitOverlay(form);

    if (overlay instanceof HTMLElement) {
        overlay.classList.remove(
            'is-visible'
        );

        overlay.setAttribute(
            'aria-hidden',
            'true'
        );
    }

    document.body.classList.remove(
        'sr12-submit-locked'
    );
};

for (const element of forms) {
    if (!(element instanceof HTMLFormElement)) {
        continue;
    }

    const form = element;

    /*
     * Simpan referensi overlay sebelum dipindahkan
     * dari form ke body.
     */
    getSubmitOverlay(form);

    form.addEventListener(
        'submit',
        (event) => {
            if (
                form.dataset.submitting ===
                'true'
            ) {
                event.preventDefault();

                return;
            }

            showLoadingState(form);
        }
    );
}

window.addEventListener(
    'pageshow',
    () => {
        for (const element of forms) {
            if (
                element instanceof
                HTMLFormElement
            ) {
                resetLoadingState(element);
            }
        }
    }
);
