<x-layouts.app title="Import Transaksi">
    <div class="sr12-page mx-auto max-w-5xl space-y-6">
        <header class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
            <div>
                <h1 class="text-2xl font-bold text-gray-900">
                    Import Data Transaksi
                </h1>

                <p class="mt-1 text-sm text-gray-600">
                    Unggah data transaksi menggunakan template sistem.
                </p>
            </div>

            <div class="flex flex-wrap gap-2">
                <x-ui.button
                    :href="route('admin.transaksi.index')"
                    variant="secondary"
                >
                    Kembali
                </x-ui.button>

                <x-ui.button
                    :href="route('admin.transaksi.template')"
                    variant="secondary"
                >
                    Download Template
                </x-ui.button>
            </div>
        </header>

        @if ($errors->has('file_transaksi'))
            <div
                class="rounded-xl border border-red-200 bg-red-50 px-5 py-4 text-sm text-red-700"
                role="alert"
                aria-live="assertive"
            >
                <p class="font-semibold">
                    Import dibatalkan karena ditemukan kesalahan:
                </p>

                <ul class="mt-3 list-disc space-y-1 pl-5">
                    @foreach ($errors->get('file_transaksi') as $message)
                        <li>{{ $message }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        <section class="rounded-lg bg-white p-6 shadow sm:p-8">
            <form
                action="{{ route('admin.transaksi.import.store') }}"
                method="POST"
                enctype="multipart/form-data"
                class="space-y-6"
                data-submit-once
                data-submit-once-label="Sedang mengimport..."
            >
                @csrf


                <div
                    class="sr12-submit-overlay"
                    role="status"
                    aria-live="assertive"
                    aria-hidden="true"
                    data-submit-overlay
                >
                    <div class="sr12-submit-overlay__viewport">
                        <div class="w-full max-w-md rounded-2xl border border-white/60 bg-white p-6 shadow-2xl sm:p-7">
                            <div class="flex items-center gap-4">
                                <div
                                    class="flex h-12 w-12 shrink-0 items-center justify-center rounded-full bg-rose-50 text-rose-700"
                                    aria-hidden="true"
                                >
                                    <svg
                                        class="h-7 w-7 animate-spin"
                                        viewBox="0 0 24 24"
                                        fill="none"
                                    >
                                        <circle
                                            class="opacity-25"
                                            cx="12"
                                            cy="12"
                                            r="9"
                                            stroke="currentColor"
                                            stroke-width="3"
                                        />

                                        <path
                                            class="opacity-90"
                                            d="M21 12a9 9 0 0 0-9-9"
                                            stroke="currentColor"
                                            stroke-width="3"
                                            stroke-linecap="round"
                                        />
                                    </svg>
                                </div>

                                <div class="min-w-0">
                                    <h2 class="text-lg font-bold text-gray-900">
                                        Memproses import transaksi
                                    </h2>

                                    <p class="mt-1 truncate text-sm font-medium text-rose-700">
                                        <span data-submit-file-name>
                                            File transaksi sedang disiapkan.
                                        </span>
                                    </p>
                                </div>
                            </div>

                            <p class="mt-4 text-sm leading-6 text-gray-600">
                                Sistem sedang membaca, memvalidasi, dan
                                menyimpan data secara bertahap.
                            </p>

                            <div
                                class="sr12-import-progress mt-5 h-1.5 overflow-hidden rounded-full bg-rose-100"
                                aria-hidden="true"
                            >
                                <span class="block h-full w-1/3 rounded-full bg-rose-600"></span>
                            </div>

                            <p class="mt-4 text-xs leading-5 text-gray-500">
                                Jangan tutup atau muat ulang halaman sampai
                                hasil import ditampilkan.
                            </p>
                        </div>
                    </div>
                </div>

                <div>
                    <label
                        for="file-transaksi"
                        class="mb-2 block text-sm font-semibold text-gray-800"
                    >
                        File Transaksi
                    </label>

                    <div class="rounded-xl border border-dashed border-blue-200 bg-blue-50/40 p-4">
                        <input
                            id="file-transaksi"
                            type="file"
                            name="file_transaksi"
                            accept=".csv,text/csv"
                            required
                            aria-describedby="file-transaksi-help"
                            class="block w-full rounded-xl border border-gray-300 bg-white px-3 py-3 text-sm text-gray-700 file:mr-4 file:rounded-lg file:border-0 file:bg-blue-50 file:px-4 file:py-2.5 file:text-sm file:font-semibold file:text-blue-700 hover:file:bg-blue-100 focus:border-blue-500 focus:outline-none focus:ring-2 focus:ring-blue-200"
                        >

                        <p
                            id="file-transaksi-help"
                            class="mt-2 text-xs text-gray-500"
                        >
                            Gunakan template CSV yang disediakan sistem dan
                            isi data mulai baris kedua tanpa mengubah susunan kolom.
                        </p>
                    </div>
                </div>

                <div class="flex justify-end">
                    <x-ui.button>
                        Validasi dan Import
                    </x-ui.button>
                </div>
            </form>
        </section>
    </div>
</x-layouts.app>
