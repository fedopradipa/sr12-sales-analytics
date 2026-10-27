const calibrationDialog = document.querySelector(
    '[data-des-calibration-modal]',
);

if (calibrationDialog instanceof HTMLDialogElement) {
    const splitInput = calibrationDialog.querySelector(
        '[data-des-calibration-split]',
    );
    const testInput = calibrationDialog.querySelector(
        '[data-des-calibration-test-months]',
    );
    const trainMonthsLabel = calibrationDialog.querySelector(
        '[data-des-train-months]',
    );
    const testMonthsLabel = calibrationDialog.querySelector(
        '[data-des-test-months-label]',
    );
    const trainBar = calibrationDialog.querySelector(
        '[data-des-train-bar]',
    );
    const testBar = calibrationDialog.querySelector(
        '[data-des-test-bar]',
    );
    const trainPeriod = calibrationDialog.querySelector(
        '[data-des-train-period]',
    );
    const testPeriod = calibrationDialog.querySelector(
        '[data-des-test-period]',
    );

    const totalMonths = Number.parseInt(
        calibrationDialog.dataset.totalMonths ?? '',
        10,
    );

    const startDate =
        calibrationDialog.dataset.startDate ?? '';
    const endDate =
        calibrationDialog.dataset.endDate ?? '';

    const parseMonth = (value) => {
        const match = /^(\d{4})-(\d{2})-\d{2}$/.exec(
            value,
        );

        if (!match) {
            return null;
        }

        return new Date(
            Date.UTC(
                Number.parseInt(match[1], 10),
                Number.parseInt(match[2], 10) - 1,
                1,
            ),
        );
    };

    const addMonths = (date, months) => new Date(
        Date.UTC(
            date.getUTCFullYear(),
            date.getUTCMonth() + months,
            1,
        ),
    );

    const monthFormatter = new Intl.DateTimeFormat(
        'id-ID',
        {
            month: 'short',
            year: 'numeric',
            timeZone: 'UTC',
        },
    );

    const formatMonth = (date) => monthFormatter
        .format(date)
        .replace('.', '');

    const renderSplit = () => {
        if (
            !(splitInput instanceof HTMLInputElement)
            || !(testInput instanceof HTMLInputElement)
            || !Number.isInteger(totalMonths)
            || totalMonths < 3
        ) {
            return;
        }

        const trainMonths = Number.parseInt(
            splitInput.value,
            10,
        );
        const testMonths = totalMonths - trainMonths;

        testInput.value = String(testMonths);

        if (trainMonthsLabel) {
            trainMonthsLabel.textContent =
                String(trainMonths);
        }

        if (testMonthsLabel) {
            testMonthsLabel.textContent =
                String(testMonths);
        }

        const trainPercent =
            trainMonths / totalMonths * 100;
        const testPercent =
            testMonths / totalMonths * 100;

        if (trainBar instanceof HTMLElement) {
            trainBar.style.width =
                `${trainPercent}%`;
        }

        if (testBar instanceof HTMLElement) {
            testBar.style.width =
                `${testPercent}%`;
        }

        const start = parseMonth(startDate);
        const end = parseMonth(endDate);

        if (!start || !end) {
            return;
        }

        const trainEnd = addMonths(
            start,
            trainMonths - 1,
        );
        const testStart = addMonths(
            start,
            trainMonths,
        );

        if (trainPeriod) {
            trainPeriod.textContent =
                `${formatMonth(start)} – `
                + `${formatMonth(trainEnd)}`;
        }

        if (testPeriod) {
            testPeriod.textContent =
                `${formatMonth(testStart)} – `
                + `${formatMonth(end)}`;
        }
    };

    if (splitInput instanceof HTMLInputElement) {
        splitInput.addEventListener(
            'input',
            renderSplit,
        );
    }

    document
        .querySelectorAll('[data-des-calibration-open]')
        .forEach((button) => {
            button.addEventListener('click', () => {
                renderSplit();

                if (!calibrationDialog.open) {
                    calibrationDialog.showModal();
                }

                if (
                    splitInput
                    instanceof HTMLInputElement
                ) {
                    splitInput.focus();
                }
            });
        });

    calibrationDialog
        .querySelectorAll('[data-des-calibration-close]')
        .forEach((button) => {
            button.addEventListener(
                'click',
                () => calibrationDialog.close(),
            );
        });

    calibrationDialog.addEventListener(
        'click',
        (event) => {
            if (event.target === calibrationDialog) {
                calibrationDialog.close();
            }
        },
    );

    renderSplit();

    if (
        calibrationDialog.dataset.openOnLoad === 'true'
        && !calibrationDialog.open
    ) {
        calibrationDialog.showModal();

        if (
            splitInput
            instanceof HTMLInputElement
        ) {
            splitInput.focus();
        }
    }
}
