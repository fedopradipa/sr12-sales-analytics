@php
    $hasAvailablePeriod =
        filled($periodeTersedia['awal'] ?? null)
        && filled($periodeTersedia['akhir'] ?? null);

    $availableStart = $hasAvailablePeriod
        ? \Illuminate\Support\Carbon::parse(
            $periodeTersedia['awal']
        )->format('Y-m-d')
        : null;

    $availableEnd = $hasAvailablePeriod
        ? \Illuminate\Support\Carbon::parse(
            $periodeTersedia['akhir']
        )->format('Y-m-d')
        : null;

    $shouldOpenModal =
        old('form_context') === 'kmeans'
        && (
            $errors->any()
            || session('error')
        );
@endphp

<x-ui.modal
    id="kmeans-form-modal"
    title="Buat Segmentasi Produk"
    maxWidth="max-w-2xl"
    data-kmeans-modal
    data-kmeans-open-on-load="{{ $shouldOpenModal ? 'true' : 'false' }}"
>
    @if (session('error'))
        <div
            class="mb-5 rounded border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-800"
            role="alert"
        >
            {!! nl2br(e(session('error'))) !!}
        </div>
    @endif

    @if ($errors->any())
        <div
            class="mb-5 rounded border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-800"
            role="alert"
        >
            <p class="font-semibold">
                Data periode belum valid.
            </p>

            <ul class="mt-2 list-disc pl-5">
                @foreach ($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    @if (! $hasAvailablePeriod)
        <div
            class="rounded border border-yellow-200 bg-yellow-50 px-4 py-3 text-sm text-yellow-800"
            role="status"
        >
            Belum ada transaksi yang dapat dianalisis.
        </div>

        <div class="mt-5 flex justify-end">
            <x-ui.button
                type="button"
                variant="secondary"
                data-modal-close
            >
                Tutup
            </x-ui.button>
        </div>
    @else
        <form
            action="{{ route('admin.kmeans.store') }}"
            method="POST"
            class="space-y-5"
            data-submit-once
        >
            @csrf

            <input
                type="hidden"
                name="form_context"
                value="kmeans"
            >

            <div
                class="rounded border border-blue-200 bg-blue-50 px-4 py-3 text-sm leading-6 text-blue-800"
            >
                Data transaksi tersedia dari
                <strong>
                    {{
                        \Illuminate\Support\Carbon::parse(
                            $availableStart
                        )->translatedFormat('d F Y')
                    }}
                </strong>
                sampai
                <strong>
                    {{
                        \Illuminate\Support\Carbon::parse(
                            $availableEnd
                        )->translatedFormat('d F Y')
                    }}
                </strong>.
            </div>

            <div class="grid gap-5 sm:grid-cols-2">
                <div>
                    <label
                        for="kmeans-periode-awal"
                        class="mb-2 block text-sm font-medium text-gray-700"
                    >
                        Periode Awal
                    </label>

                    <input
                        id="kmeans-periode-awal"
                        name="awal"
                        type="date"
                        min="{{ $availableStart }}"
                        max="{{ $availableEnd }}"
                        value="{{
                            old(
                                'awal',
                                $availableStart
                            )
                        }}"
                        aria-invalid="{{
                            $errors->has('awal')
                                ? 'true'
                                : 'false'
                        }}"
                        class="w-full rounded border border-gray-300 px-3 py-2 text-sm focus:border-rose-500 focus:outline-none focus:ring-2 focus:ring-rose-200"
                    >

                    @foreach ($errors->get('awal') as $message)
                        <p class="mt-2 text-xs text-red-600">
                            {{ $message }}
                        </p>
                    @endforeach
                </div>

                <div>
                    <label
                        for="kmeans-periode-akhir"
                        class="mb-2 block text-sm font-medium text-gray-700"
                    >
                        Periode Akhir
                    </label>

                    <input
                        id="kmeans-periode-akhir"
                        name="akhir"
                        type="date"
                        min="{{ $availableStart }}"
                        max="{{ $availableEnd }}"
                        value="{{
                            old(
                                'akhir',
                                $availableEnd
                            )
                        }}"
                        aria-invalid="{{
                            $errors->has('akhir')
                                ? 'true'
                                : 'false'
                        }}"
                        class="w-full rounded border border-gray-300 px-3 py-2 text-sm focus:border-rose-500 focus:outline-none focus:ring-2 focus:ring-rose-200"
                    >

                    @foreach ($errors->get('akhir') as $message)
                        <p class="mt-2 text-xs text-red-600">
                            {{ $message }}
                        </p>
                    @endforeach
                </div>
            </div>

            <div
                class="rounded border border-rose-200 bg-rose-50 px-4 py-3 text-sm leading-6 text-rose-800"
            >
                Sistem menghitung Recency, Frequency, Quantity,
                dan Monetary dari transaksi pada periode yang dipilih,
                kemudian membentuk tiga kelompok prioritas produk.
            </div>

            <div class="flex justify-end gap-2">
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
                    Proses Segmentasi
                </x-ui.button>
            </div>
        </form>
    @endif
</x-ui.modal>

<script type="module">
    const dialog = document.getElementById(
        'kmeans-form-modal'
    );

    if (!(dialog instanceof HTMLDialogElement)) {
        throw new Error(
            'Modal segmentasi produk tidak ditemukan.'
        );
    }

    const openDialog = () => {
        if (!dialog.open) {
            dialog.showModal();
        }

        window.requestAnimationFrame(() => {
            const firstInput = dialog.querySelector(
                'input:not([type="hidden"]):not([disabled])'
            );

            if (firstInput instanceof HTMLElement) {
                firstInput.focus();
            }
        });
    };

    document
        .querySelectorAll(
            '[data-kmeans-modal-open="kmeans-form-modal"]'
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
        dialog.dataset.kmeansOpenOnLoad === 'true'
    ) {
        openDialog();
    }
</script>
