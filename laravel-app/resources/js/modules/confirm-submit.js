// @ts-check

/**
 * @param {Document|HTMLElement} root
 * @returns {void}
 */
export const initializeSubmitConfirmations = (
    root = document
) => {
    root.addEventListener('submit', (event) => {
        const submittedForm = event.target;

        if (
            !(submittedForm instanceof HTMLFormElement)
            || !submittedForm.matches('[data-confirm-submit]')
        ) {
            return;
        }

        const message =
            submittedForm.dataset.confirmMessage
            ?? 'Yakin ingin melanjutkan tindakan ini?';

        if (!window.confirm(message)) {
            event.preventDefault();
        }
    });
};

initializeSubmitConfirmations();