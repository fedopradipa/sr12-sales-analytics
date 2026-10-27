<x-layouts.app title="Penentuan Parameter">
    <div class="sr12-page space-y-6">
        @if (session('success'))
            <div
                class="rounded border border-green-200 bg-green-50 px-4 py-3 text-sm text-green-800"
                role="status"
            >
                {{ session('success') }}
            </div>
        @endif

        @if (session('error'))
            <div
                class="rounded border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-800"
                role="alert"
            >
                {{ session('error') }}
            </div>
        @endif

        <section class="rounded-lg bg-white p-6 shadow">
            <div
                class="flex flex-wrap items-start justify-between gap-4"
            >
                <div class="max-w-3xl">
                    <h1 class="text-xl font-semibold text-gray-900">
                        Penentuan Parameter DES
                    </h1>

                    <p class="mt-2 text-sm leading-6 text-gray-600">
                        Evaluasi parameter Double Exponential
                        Smoothing Brown menggunakan pembagian data
                        Train dan Test secara kronologis. Sistem
                        menguji alpha 0,01 sampai 0,99 untuk setiap
                        produk dan memilih alpha dengan WAPE Train terendah.
                    </p>
                </div>

                @if ($predictionContext !== null)
                    <x-ui.button
                        type="button"
                        data-des-calibration-open
                    >
                        Tentukan Parameter
                    </x-ui.button>
                @endif
            </div>

            @if ($predictionContext !== null)
                <div
                    class="mt-5 grid gap-3 sm:grid-cols-3"
                >
                    <div
                        class="rounded-lg border border-gray-200 bg-gray-50 p-4"
                    >
                        <p
                            class="text-xs font-medium uppercase tracking-wide text-gray-500"
                        >
                            Data Tersedia
                        </p>

                        <p
                            class="mt-1 text-sm font-semibold text-gray-900"
                        >
                            {{ $predictionContext['awal'] }}
                            –
                            {{ $predictionContext['akhir'] }}
                        </p>
                    </div>

                    <div
                        class="rounded-lg border border-gray-200 bg-gray-50 p-4"
                    >
                        <p
                            class="text-xs font-medium uppercase tracking-wide text-gray-500"
                        >
                            Jumlah Data
                        </p>

                        <p
                            class="mt-1 text-sm font-semibold text-gray-900"
                        >
                            {{ $predictionContext['jumlah_bulan'] }}
                            bulan
                        </p>
                    </div>

                    <div
                        class="rounded-lg border border-gray-200 bg-gray-50 p-4"
                    >
                        <p
                            class="text-xs font-medium uppercase tracking-wide text-gray-500"
                        >
                            Pemilihan Alpha
                        </p>

                        <p
                            class="mt-1 text-sm font-semibold text-gray-900"
                        >
                            WAPE Train terendah
                        </p>
                    </div>
                </div>

                <p class="mt-4 text-xs leading-5 text-gray-500">
                    Geser batas Train/Test saat menentukan parameter. Bagian kiri slider adalah Train dan
                    bagian kanan adalah Test. MAE dan WAPE Train/Test
                    disimpan bersama alpha aktif sebagai hasil
                    evaluasi terakhir.
                </p>
            @else
                <div
                    class="mt-5 rounded-lg border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-800"
                    role="status"
                >
                    Belum tersedia data transaksi bulanan yang dapat
                    digunakan untuk penentuan parameter.
                </div>
            @endif
        </section>

        @if (
            $calibrationResult !== null
            && (int) ($calibrationResult['skipped'] ?? 0) > 0
        )
            @include(
                'admin.des.partials.calibration-skipped-products',
                ['calibrationResult' => $calibrationResult]
            )
        @endif

        @include('admin.des.partials.active-parameters', ['activeParameters' => $activeParameters])
    </div>

    @if ($predictionContext !== null)
        @include('admin.des.partials.calibration-modal')
    @endif
</x-layouts.app>
