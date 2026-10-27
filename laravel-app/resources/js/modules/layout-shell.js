// @ts-check

const body = document.body;
const sidebar = document.getElementById('sr12-sidebar');

const toggleButtons = document.querySelectorAll(
    '[data-sidebar-toggle]'
);

const closeButtons = document.querySelectorAll(
    '[data-sidebar-close]'
);

const desktopMedia = window.matchMedia(
    '(min-width: 1024px)'
);

const storageKey = 'sr12-sidebar-collapsed';

/**
 * @returns {boolean}
 */
const readStoredCollapsedState = () => {
    try {
        return window.localStorage.getItem(
            storageKey
        ) === 'true';
    } catch {
        return false;
    }
};

/**
 * @param {boolean} collapsed
 * @returns {void}
 */
const saveCollapsedState = (collapsed) => {
    try {
        window.localStorage.setItem(
            storageKey,
            String(collapsed)
        );
    } catch {
        // Sidebar tetap berfungsi bila storage diblokir.
    }
};

/**
 * @returns {boolean}
 */
const sidebarIsExpanded = () => {
    if (desktopMedia.matches) {
        return !body.classList.contains(
            'sidebar-collapsed'
        );
    }

    return body.classList.contains(
        'sidebar-open'
    );
};

/**
 * Menyelaraskan atribut aksesibilitas.
 *
 * @returns {void}
 */
const synchronizeState = () => {
    const expanded = sidebarIsExpanded();

    toggleButtons.forEach((button) => {
        button.setAttribute(
            'aria-expanded',
            String(expanded)
        );

        button.setAttribute(
            'aria-label',
            expanded
                ? 'Tutup navigasi'
                : 'Buka navigasi'
        );
    });

    if (sidebar) {
        sidebar.setAttribute(
            'aria-hidden',
            String(!expanded)
        );
    }
};

/**
 * @returns {void}
 */
const toggleSidebar = () => {
    if (desktopMedia.matches) {
        body.classList.remove(
            'sidebar-open'
        );

        body.classList.toggle(
            'sidebar-collapsed'
        );

        saveCollapsedState(
            body.classList.contains(
                'sidebar-collapsed'
            )
        );
    } else {
        body.classList.remove(
            'sidebar-collapsed'
        );

        body.classList.toggle(
            'sidebar-open'
        );
    }

    synchronizeState();
};

/**
 * @returns {void}
 */
const closeSidebar = () => {
    if (desktopMedia.matches) {
        body.classList.remove(
            'sidebar-open'
        );

        body.classList.add(
            'sidebar-collapsed'
        );

        saveCollapsedState(true);
    } else {
        body.classList.remove(
            'sidebar-open'
        );
    }

    synchronizeState();
};

/**
 * Menyesuaikan state ketika breakpoint berubah.
 *
 * @returns {void}
 */
const applyBreakpointState = () => {
    if (desktopMedia.matches) {
        body.classList.remove(
            'sidebar-open'
        );

        body.classList.toggle(
            'sidebar-collapsed',
            readStoredCollapsedState()
        );
    } else {
        body.classList.remove(
            'sidebar-collapsed'
        );

        body.classList.remove(
            'sidebar-open'
        );
    }

    synchronizeState();
};

toggleButtons.forEach((button) => {
    button.addEventListener(
        'click',
        toggleSidebar
    );
});

closeButtons.forEach((button) => {
    button.addEventListener(
        'click',
        closeSidebar
    );
});

sidebar?.querySelectorAll('a').forEach((link) => {
    link.addEventListener('click', () => {
        if (!desktopMedia.matches) {
            body.classList.remove(
                'sidebar-open'
            );

            synchronizeState();
        }
    });
});

document.addEventListener('keydown', (event) => {
    if (
        event.key === 'Escape'
        && !desktopMedia.matches
    ) {
        body.classList.remove(
            'sidebar-open'
        );

        synchronizeState();
    }
});

desktopMedia.addEventListener(
    'change',
    applyBreakpointState
);

applyBreakpointState();
