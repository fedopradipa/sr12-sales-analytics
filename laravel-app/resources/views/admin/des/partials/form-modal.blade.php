@php
    $defaultHasilId = old(
        'kmeans_id',
        $selectionOptions[0]['id'] ?? ''
    );

    $oldCluster = old('cluster');
    $oldProductId = old('produk_id');

    $canForecast =
        $selectionOptions !== []
        && $predictionContext !== null;

    $shouldOpenModal =
        request()->boolean('open_modal')
        || (
            old('form_context') === 'des'
            && (
                $errors->any()
                || session('error')
            )
        );
@endphp

<x-ui.modal
    id="des-form-modal"
    title="Buat Peramalan Penjualan"
    maxWidth="max-w-3xl"
    style="max-width: 56rem;"
    data-des-modal
    data-des-open-on-load="{{ $shouldOpenModal ? 'true' : 'false' }}"
>
    <div class="max-h-[calc(100vh-10rem)] overflow-y-auto pr-1">
        @if (session('error'))
            <div
                class="mb-5 rounded border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-800"
                role="alert"
            >
                {!! nl2br(e(session('error'))) !!}
            </div>
        @endif

        @if (! $canForecast)
            <div
                class="rounded border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-800"
            >
                Hasil segmentasi atau data penjualan belum tersedia.
            </div>
        @else
            <form
                id="des-forecast-form"
                action="{{ route('admin.des.store') }}"
                method="POST"
                class="grid gap-5 md:grid-cols-2"
                data-submit-once
                data-submit-once-label="Memproses..."
            >
                @csrf

                <input
                    type="hidden"
                    name="form_context"
                    value="des"
                >

                <div class="md:col-span-2">
                    <label
                        for="des-hasil-kmeans-id"
                        class="mb-2 block text-sm font-medium text-gray-700"
                    >
                        Hasil Segmentasi
                    </label>

                    <select
                        id="des-hasil-kmeans-id"
                        name="kmeans_id"
                        class="w-full rounded border border-gray-300 px-3 py-2 text-sm focus:border-rose-500 focus:outline-none focus:ring-2 focus:ring-rose-200"
                        required
                    >
                        @foreach ($selectionOptions as $hasil)
                            <option
                                value="{{ $hasil['id'] }}"
                                @selected(
                                    (string) $defaultHasilId
                                    === (string) $hasil['id']
                                )
                            >
                                {{ $hasil['label'] }}
                            </option>
                        @endforeach
                    </select>

                    @error('kmeans_id')
                        <p class="mt-2 text-xs font-medium text-red-600">
                            {{ $message }}
                        </p>
                    @enderror
                </div>

                <div class="md:col-span-2">
                    <label
                        for="des-cluster"
                        class="mb-2 block text-sm font-medium text-gray-700"
                    >
                        Kelompok Produk
                    </label>

                    <select
                        id="des-cluster"
                        name="cluster"
                        class="w-full rounded border border-gray-300 px-3 py-2 text-sm focus:border-rose-500 focus:outline-none focus:ring-2 focus:ring-rose-200"
                        required
                    ></select>

                    <p
                        id="des-cluster-count"
                        class="mt-2 text-xs font-medium text-rose-700"
                    ></p>

                    <div
                        class="mt-3 rounded-lg border border-gray-200 bg-gray-50 px-4 py-3 text-xs leading-5 text-gray-600"
                        data-des-readiness
                    ></div>

                    @error('cluster')
                        <p class="mt-2 text-xs font-medium text-red-600">
                            {{ $message }}
                        </p>
                    @enderror
                </div>

                <div class="md:col-span-2">
                    <label
                        for="des-produk-id"
                        class="mb-2 block text-sm font-medium text-gray-700"
                    >
                        Target Produk
                    </label>

                    <select
                        id="des-produk-id"
                        name="produk_id"
                        class="w-full rounded border border-gray-300 px-3 py-2 text-sm focus:border-rose-500 focus:outline-none focus:ring-2 focus:ring-rose-200"
                    ></select>

                    <p class="mt-2 text-xs leading-5 text-gray-500">
                        Pilih <strong>Seluruh Produk</strong> untuk
                        meramalkan semua produk yang siap pada kelompok,
                        atau pilih satu produk tertentu.
                    </p>

                    @error('produk_id')
                        <p class="mt-2 text-xs font-medium text-red-600">
                            {{ $message }}
                        </p>
                    @enderror
                </div>

                <div class="rounded border border-gray-200 bg-gray-50 px-4 py-3">
                    <p class="text-xs font-semibold uppercase tracking-wide text-gray-500">
                        Periode Data Penjualan
                    </p>
                    <p class="mt-1 text-sm font-semibold text-gray-900">
                        {{ $predictionContext['awal'] }}
                        s.d.
                        {{ $predictionContext['akhir'] }}
                    </p>
                </div>

                <div class="rounded border border-rose-200 bg-rose-50 px-4 py-3">
                    <p class="text-xs font-semibold uppercase tracking-wide text-rose-700">
                        Periode yang Diramalkan
                    </p>
                    <p class="mt-1 text-sm font-semibold text-gray-900">
                        {{ $predictionContext['target'] }}
                    </p>
                    <p class="mt-2 text-xs leading-5 text-gray-600">
                        Sistem selalu meramalkan satu bulan berikutnya dari periode data penjualan.
                    </p>
                </div>

                <div
                    class="md:col-span-2 flex justify-end gap-2 border-t border-gray-200 pt-4"
                >
                    <x-ui.button
                        type="button"
                        variant="secondary"
                        data-modal-close
                    >
                        Batal
                    </x-ui.button>

                    <x-ui.button
                        type="submit"
                        variant="primary"
                    >
                        Proses Peramalan
                    </x-ui.button>
                </div>
            </form>
        @endif
    </div>
</x-ui.modal>

<script type="module">
    const dialog = document.getElementById(
        'des-form-modal'
    );

    if (!(dialog instanceof HTMLDialogElement)) {
        throw new Error(
            'Modal peramalan DES tidak ditemukan.'
        );
    }

    const canForecast = @json($canForecast);
    const selectionOptions = @json($selectionOptions);
    const initialHasilId = @json(
        (string) $defaultHasilId
    );
    const initialCluster = @json(
        $oldCluster === null
            ? null
            : (string) $oldCluster
    );
    const initialProduct = @json(
        $oldProductId === null
            ? ''
            : (string) $oldProductId
    );

    const hasilSelect = document.getElementById(
        'des-hasil-kmeans-id'
    );
    const clusterSelect = document.getElementById(
        'des-cluster'
    );
    const clusterCount = document.getElementById(
        'des-cluster-count'
    );
    const productSelect = document.getElementById(
        'des-produk-id'
    );
    const readiness = dialog.querySelector(
        '[data-des-readiness]'
    );

    let firstOpen = true;

    const selectedResult = () => {
        if (!(hasilSelect instanceof HTMLSelectElement)) {
            return null;
        }

        return selectionOptions.find(
            (item) =>
                String(item.id) === hasilSelect.value
        ) ?? null;
    };

    const selectedCluster = () => {
        if (!(clusterSelect instanceof HTMLSelectElement)) {
            return null;
        }

        return selectedResult()?.clusters.find(
            (item) =>
                String(item.cluster)
                === clusterSelect.value
        ) ?? null;
    };

    const selectedProduct = () => {
        if (
            !(productSelect instanceof HTMLSelectElement)
            || productSelect.value === ''
        ) {
            return null;
        }

        return (selectedCluster()?.products ?? []).find(
            (product) =>
                String(product.id) === productSelect.value
        ) ?? null;
    };

    const updateReadiness = () => {
        const products =
            selectedCluster()?.products ?? [];

        const ready = products.filter(
            (product) => product.has_parameter === true
        ).length;

        const skipped = products.length - ready;
        const product = selectedProduct();

        if (clusterCount instanceof HTMLElement) {
            clusterCount.textContent =
                `Kelompok ini berisi ${products.length} produk aktif.`;
        }

        if (!(readiness instanceof HTMLElement)) {
            return;
        }

        if (product !== null) {
            readiness.textContent =
                product.has_parameter === true
                    ? `${product.kode} siap diramalkan secara individual.`
                    : `${product.kode} belum memiliki parameter DES `
                        + `dan tidak dapat diramalkan secara individual.`;

            return;
        }

        readiness.textContent = skipped > 0
            ? `${ready} produk siap diramalkan. `
                + `${skipped} produk belum memiliki parameter DES `
                + `dan akan dilewati.`
            : `Seluruh ${ready} produk memiliki parameter DES `
                + `dan siap diramalkan.`;
    };

    const populateProducts = (
        preferredProduct = ''
    ) => {
        if (!(productSelect instanceof HTMLSelectElement)) {
            return;
        }

        const products =
            selectedCluster()?.products ?? [];

        productSelect.replaceChildren();

        const allOption =
            document.createElement('option');

        allOption.value = '';
        allOption.textContent =
            `Seluruh Produk (${products.length} produk)`;

        productSelect.append(allOption);

        for (const product of products) {
            const option =
                document.createElement('option');

            option.value = String(product.id);
            option.textContent =
                `${product.kode} — ${product.nama}`
                + (
                    product.has_parameter === true
                        ? ''
                        : ' — parameter belum tersedia'
                );

            productSelect.append(option);
        }

        const exists = [
            ...productSelect.options,
        ].some(
            (option) =>
                option.value
                === String(preferredProduct)
        );

        productSelect.value = exists
            ? String(preferredProduct)
            : '';

        updateReadiness();
    };

    const populateClusters = (
        preferredCluster = null,
        preferredProduct = ''
    ) => {
        if (!(clusterSelect instanceof HTMLSelectElement)) {
            return;
        }

        const clusters =
            selectedResult()?.clusters ?? [];

        clusterSelect.replaceChildren();

        for (const cluster of clusters) {
            const option =
                document.createElement('option');

            option.value =
                String(cluster.cluster);

            option.textContent =
                `Cluster ${cluster.cluster}`
                + ` — ${cluster.label}`;

            clusterSelect.append(option);
        }

        const exists = [
            ...clusterSelect.options,
        ].some(
            (option) =>
                option.value
                === String(preferredCluster)
        );

        if (exists) {
            clusterSelect.value =
                String(preferredCluster);
        }

        populateProducts(
            preferredProduct
        );
    };

    if (
        canForecast
        && hasilSelect instanceof HTMLSelectElement
        && clusterSelect instanceof HTMLSelectElement
        && productSelect instanceof HTMLSelectElement
    ) {
        hasilSelect.addEventListener(
            'change',
            () => populateClusters()
        );

        clusterSelect.addEventListener(
            'change',
            () => populateProducts()
        );

        productSelect.addEventListener(
            'change',
            updateReadiness
        );
    }

    const openDialog = () => {
        if (
            canForecast
            && hasilSelect instanceof HTMLSelectElement
        ) {
            if (firstOpen) {
                const resultExists = [
                    ...hasilSelect.options,
                ].some(
                    (option) =>
                        option.value
                        === initialHasilId
                );

                if (resultExists) {
                    hasilSelect.value =
                        initialHasilId;
                }

                populateClusters(
                    initialCluster,
                    initialProduct
                );

                firstOpen = false;
            } else {
                populateClusters(
                    clusterSelect instanceof
                        HTMLSelectElement
                        ? clusterSelect.value
                        : null,
                    productSelect instanceof
                        HTMLSelectElement
                        ? productSelect.value
                        : ''
                );
            }
        }

        if (! dialog.open) {
            dialog.showModal();
        }
    };

    document
        .querySelectorAll(
            '[data-des-modal-open="des-form-modal"]'
        )
        .forEach((trigger) => {
            trigger.addEventListener(
                'click',
                openDialog
            );
        });

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

    if (
        dialog.dataset.desOpenOnLoad === 'true'
        || dialog.dataset.openOnLoad === 'true'
    ) {
        openDialog();
    }
</script>
