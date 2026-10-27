@php
    $openCalibrationModal = $errors->has('test_months');

    $totalMonths = max(
        3,
        (int) (
            $predictionContext['jumlah_bulan']
            ?? 3
        )
    );

    $minimumTrainMonths = 2;
    $maximumTestMonths = max(
        1,
        $totalMonths - $minimumTrainMonths
    );

    $defaultTestMonths = min(
        8,
        $maximumTestMonths
    );

    $testMonths = (int) old(
        'test_months',
        $defaultTestMonths
    );

    $testMonths = min(
        max(1, $testMonths),
        $maximumTestMonths
    );

    $trainMonths = $totalMonths - $testMonths;
@endphp

<dialog
    class="m-auto w-[calc(100%_-_2rem)] max-w-2xl rounded-2xl bg-transparent p-0 shadow-[0_35px_100px_rgba(77,36,43,.34)] backdrop:bg-[#382b31]/55 backdrop:backdrop-blur-sm"
    data-des-calibration-modal
    data-open-on-load="{{ $openCalibrationModal ? 'true' : 'false' }}"
    data-total-months="{{ $totalMonths }}"
    data-start-date="{{ $predictionContext['awal_iso'] ?? '' }}"
    data-end-date="{{ $predictionContext['akhir_iso'] ?? '' }}"
>
    <div
        class="overflow-hidden rounded-2xl border border-rose-100 bg-white"
    >
        <header
            class="border-b border-rose-100 bg-gradient-to-r from-rose-50 to-white px-6 py-4"
        >
            <div>
                <h2 class="text-lg font-semibold text-gray-900">
                    Penentuan Parameter DES
                </h2>

                <p class="mt-1 text-sm text-gray-600">
                    Tentukan pembagian data Train dan Test
                    secara kronologis sebelum penentuan parameter.
                </p>
            </div>

        </header>

        <form
            method="POST"
            action="{{ route('admin.kalibrasi-parameter.store') }}"
            class="space-y-5 p-6"
    data-submit-once
    data-submit-once-label="Memproses..."
>
            @csrf

            <input
                type="hidden"
                name="test_months"
                value="{{ $testMonths }}"
                data-des-calibration-test-months
            >

            <section
                class="rounded-xl border border-rose-100 bg-rose-50/40 p-5"
                aria-labelledby="des-split-title"
            >
                <div
                    class="flex items-start justify-between gap-4"
                >
                    <div>
                        <h3
                            id="des-split-title"
                            class="text-sm font-semibold text-gray-900"
                        >
                            Pembagian Data
                        </h3>

                        <p
                            class="mt-1 text-xs leading-5 text-gray-500"
                        >
                            Geser titik pembatas untuk menentukan
                            jumlah bulan Train dan Test.
                        </p>
                    </div>

                    <div
                        class="rounded-full border border-rose-100 bg-white px-3 py-1 text-xs font-medium text-gray-600"
                    >
                        Total
                        <span data-des-total-months>
                            {{ $totalMonths }}
                        </span>
                        bulan
                    </div>
                </div>

                <div class="mt-5 grid grid-cols-2 gap-4">
                    <div
                        class="rounded-lg border border-gray-200 bg-white p-4"
                    >
                        <p
                            class="text-xs font-semibold uppercase tracking-wide text-gray-500"
                        >
                            Train
                        </p>

                        <p
                            class="mt-1 text-2xl font-semibold text-gray-900"
                        >
                            <span data-des-train-months>
                                {{ $trainMonths }}
                            </span>
                            <span
                                class="text-sm font-medium text-gray-500"
                            >
                                bulan
                            </span>
                        </p>
                    </div>

                    <div
                        class="rounded-lg border border-rose-200 bg-white p-4 text-right"
                    >
                        <p
                            class="text-xs font-semibold uppercase tracking-wide text-rose-600"
                        >
                            Test
                        </p>

                        <p
                            class="mt-1 text-2xl font-semibold text-rose-700"
                        >
                            <span data-des-test-months-label>
                                {{ $testMonths }}
                            </span>
                            <span
                                class="text-sm font-medium text-gray-500"
                            >
                                bulan
                            </span>
                        </p>
                    </div>
                </div>

                <div class="mt-5">
                    <div
                        class="mb-3 flex h-2 overflow-hidden rounded-full bg-gray-200"
                        aria-hidden="true"
                    >
                        <div
                            class="h-full bg-gray-700 transition-[width]"
                            style="width: {{ ($trainMonths / $totalMonths) * 100 }}%"
                            data-des-train-bar
                        ></div>

                        <div
                            class="h-full bg-rose-400 transition-[width]"
                            style="width: {{ ($testMonths / $totalMonths) * 100 }}%"
                            data-des-test-bar
                        ></div>
                    </div>

                    <input
                        id="des-split-months"
                        type="range"
                        min="{{ $minimumTrainMonths }}"
                        max="{{ max(
                            $minimumTrainMonths,
                            $totalMonths - 1
                        ) }}"
                        step="1"
                        value="{{ $trainMonths }}"
                        class="h-2 w-full cursor-pointer accent-rose-600"
                        aria-label="Batas pembagian data Train dan Test"
                        data-des-calibration-split
                    >

                    <div
                        class="mt-2 flex items-center justify-between text-[11px] font-medium uppercase tracking-wide text-gray-500"
                        aria-hidden="true"
                    >
                        <span>Train</span>
                        <span>Test</span>
                    </div>
                </div>

                <div
                    class="mt-5 grid grid-cols-1 gap-3 sm:grid-cols-2"
                >
                    <div
                        class="rounded-lg border border-gray-200 bg-white p-3"
                    >
                        <p class="text-xs font-medium text-gray-500">
                            Periode Train
                        </p>

                        <p
                            class="mt-1 text-sm font-semibold text-gray-800"
                            data-des-train-period
                        >
                            -
                        </p>
                    </div>

                    <div
                        class="rounded-lg border border-rose-100 bg-white p-3"
                    >
                        <p class="text-xs font-medium text-gray-500">
                            Periode Test
                        </p>

                        <p
                            class="mt-1 text-sm font-semibold text-gray-800"
                            data-des-test-period
                        >
                            -
                        </p>
                    </div>
                </div>

                <div
                    class="mt-4 rounded-lg border border-rose-100 bg-white px-4 py-3"
                >
                    <p class="text-xs leading-5 text-gray-500">
                        Sistem menguji alpha 0,01–0,99 untuk setiap
                        produk. Alpha dengan WAPE Train terendah dipilih,
                        kemudian alpha tersebut dikunci. Data Test hanya
                        digunakan untuk menghitung MAE dan WAPE Test.
                    </p>
                </div>

                @error('test_months')
                    <p
                        class="mt-3 text-xs font-medium text-red-600"
                    >
                        {{ $message }}
                    </p>
                @enderror
            </section>

            <div
                class="flex justify-end gap-2 border-t border-gray-200 pt-4"
            >
                <x-ui.button
                    type="button"
                    variant="secondary"
                    data-des-calibration-close
                >
                    Batal
                </x-ui.button>

                <x-ui.button>
                    Tentukan Parameter
                </x-ui.button>
            </div>
        </form>
    </div>
</dialog>
