<x-layouts.app title="Hasil Peramalan Penjualan">
    @php
        $targetLabel = $prosesDes->target
            ?->locale('id')
            ->translatedFormat('F Y') ?? '-';

        $segmentasiAwal = $prosesDes->hasilKmeans?->awal
            ?? $prosesDes->data_awal;

        $segmentasiAkhir = $prosesDes->hasilKmeans?->akhir
            ?? $prosesDes->data_akhir;

        $segmentasiAwalLabel = $segmentasiAwal
            ?->locale('id')
            ->translatedFormat('F Y') ?? '-';

        $segmentasiAkhirLabel = $segmentasiAkhir
            ?->locale('id')
            ->translatedFormat('F Y') ?? '-';

        $kelompokLabel =
            \App\Services\DesViewDataService::CLUSTER_LABELS[
                (int) $prosesDes->cluster
            ] ?? 'Kelompok Produk';

        $processStatusClass =
            $coverage['status'] === 'partial'
                ? 'border-amber-200 bg-amber-50 text-amber-800'
                : 'border-green-200 bg-green-50 text-green-800';
    @endphp

    <div class="sr12-page space-y-6">
        @if (session('success'))
            <div
                class="rounded-lg border border-green-200 bg-green-50 px-4 py-3 text-sm text-green-800"
                role="status"
            >
                {{ session('success') }}
            </div>
        @endif

        @if (session('warning'))
            <div
                class="rounded-lg border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-800"
                role="status"
            >
                {{ session('warning') }}
            </div>
        @endif

        <section class="rounded-xl border border-gray-200 bg-white p-6 shadow-sm">
            <div class="flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
                <div class="min-w-0 flex-1">
                    <div class="flex flex-wrap items-center gap-3">
                        <h1 class="text-2xl font-semibold text-gray-900">
                            Hasil Peramalan Penjualan
                        </h1>

                        <span
                            class="inline-flex rounded-full border px-3 py-1 text-xs font-semibold {{ $processStatusClass }}"
                        >
                            {{ $coverage['status_label'] }}
                        </span>
                    </div>

                    <div
                        class="mt-5 grid grid-cols-1 gap-3 sm:grid-cols-2 xl:grid-cols-6"
                    >
                        <div class="rounded-xl border border-rose-200 bg-rose-50 px-5 py-4 sm:col-span-2">
                            <p class="text-xs font-semibold uppercase tracking-wide text-rose-700">
                                Kelompok Produk
                            </p>
                            <p class="mt-1 text-xl font-semibold text-gray-900">
                                {{ $kelompokLabel }}
                            </p>
                        </div>

                        <div class="rounded-xl border border-gray-200 bg-gray-50 px-4 py-4">
                            <p class="text-xs font-semibold uppercase tracking-wide text-gray-500">
                                Periode Segmentasi
                            </p>
                            <p class="mt-1 text-sm font-semibold text-gray-900">
                                {{ $segmentasiAwalLabel }}
                                –
                                {{ $segmentasiAkhirLabel }}
                            </p>
                        </div>

                        <div class="rounded-xl border border-gray-200 bg-gray-50 px-4 py-4">
                            <p class="text-xs font-semibold uppercase tracking-wide text-gray-500">
                                Target Peramalan
                            </p>
                            <p class="mt-1 text-sm font-semibold text-gray-900">
                                {{ $targetLabel }}
                            </p>
                        </div>

                        <div class="rounded-xl border border-gray-200 bg-gray-50 px-4 py-4">
                            <p class="text-xs font-semibold uppercase tracking-wide text-gray-500">
                                Berhasil
                            </p>
                            <p class="mt-1 text-sm font-semibold text-gray-900">
                                {{ $coverage['forecasted'] }}
                                dari
                                {{ $coverage['total'] }}
                            </p>
                        </div>

                        <div class="rounded-xl border border-gray-200 bg-gray-50 px-4 py-4">
                            <p class="text-xs font-semibold uppercase tracking-wide text-gray-500">
                                Tidak Diramalkan
                            </p>
                            <p class="mt-1 text-sm font-semibold text-gray-900">
                                {{ $coverage['skipped'] }} Produk
                            </p>
                        </div>
                    </div>
                </div>

                <a
                    href="{{ route('admin.des.index') }}"
                    class="inline-flex items-center justify-center rounded-lg border border-gray-300 bg-white px-4 py-2 text-sm font-semibold text-gray-700 hover:bg-gray-50"
                >
                    Kembali
                </a>
            </div>
        </section>

        <section class="overflow-hidden rounded-xl border border-gray-200 bg-white shadow-sm">
            <div class="border-b border-gray-200 px-5 py-4">
                <h2 class="text-base font-semibold text-gray-900">
                    Daftar Hasil Peramalan
                </h2>
                <p class="mt-1 text-xs leading-5 text-gray-500">
                    Hasil dan persediaan merupakan snapshot saat
                    peramalan dibuat.
                </p>
            </div>

            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-200">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="px-5 py-3 text-left text-xs font-semibold uppercase tracking-wide text-gray-600">Kode</th>
                            <th class="px-5 py-3 text-left text-xs font-semibold uppercase tracking-wide text-gray-600">Nama Produk</th>
                            <th class="px-5 py-3 text-right text-xs font-semibold uppercase tracking-wide text-gray-600">Prediksi</th>
                            <th class="px-5 py-3 text-right text-xs font-semibold uppercase tracking-wide text-gray-600">Stok Saat Peramalan</th>
                            <th class="px-5 py-3 text-right text-xs font-semibold uppercase tracking-wide text-gray-600">Stok Minimum</th>
                            <th class="px-5 py-3 text-right text-xs font-semibold uppercase tracking-wide text-gray-600">Stok Perlu Ditambah</th>
                            <th class="px-5 py-3 text-center text-xs font-semibold uppercase tracking-wide text-gray-600">Status</th>
                        </tr>
                    </thead>

                    <tbody class="divide-y divide-gray-100 bg-white">
                        @forelse ($workspace as $item)
                            @php
                                $statusClasses = match ($item['status']) {
                                    'kritis' => 'border-red-400 bg-red-100 text-red-800',
                                    'waspada' => 'border-amber-300 bg-amber-100 text-amber-800',
                                    'aman' => 'border-green-300 bg-green-100 text-green-800',
                                    default => 'border-gray-300 bg-gray-100 text-gray-700',
                                };
                            @endphp

                            <tr class="hover:bg-gray-50/70">
                                <td class="whitespace-nowrap px-5 py-4 text-sm font-semibold text-gray-800">{{ $item['kode'] }}</td>
                                <td class="min-w-64 px-5 py-4 text-sm text-gray-800">{{ $item['nama'] }}</td>
                                <td class="whitespace-nowrap px-5 py-4 text-right text-sm font-semibold text-gray-900">{{ number_format($item['prediksi'], 2, ',', '.') }} Pcs</td>
                                <td class="whitespace-nowrap px-5 py-4 text-right text-sm text-gray-700">{{ $item['stok'] === null ? '-' : number_format($item['stok'], 0, ',', '.').' Pcs' }}</td>
                                <td class="whitespace-nowrap px-5 py-4 text-right text-sm text-gray-700">{{ $item['stok_min'] === null ? '-' : number_format($item['stok_min'], 0, ',', '.').' Pcs' }}</td>
                                <td class="whitespace-nowrap px-5 py-4 text-right text-sm font-semibold text-gray-900">{{ $item['estimasi'] === null ? '-' : number_format($item['estimasi'], 0, ',', '.').' Pcs' }}</td>
                                <td class="whitespace-nowrap px-5 py-4 text-center">
                                    <span class="inline-flex rounded-full border px-2.5 py-1 text-xs font-semibold {{ $statusClasses }}">
                                        {{ $item['status_label'] }}
                                    </span>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="7" class="px-5 py-10 text-center text-sm text-gray-500">
                                    Belum ada hasil peramalan.
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </section>

        @if ($coverage['skipped'] > 0)
            <section class="overflow-hidden rounded-xl border border-amber-200 bg-white shadow-sm">
                <div class="border-b border-amber-200 bg-amber-50 px-5 py-4">
                    <h2 class="text-base font-semibold text-amber-900">
                        Produk Tidak Diramalkan
                    </h2>
                    <p class="mt-1 text-xs leading-5 text-amber-800">
                        Produk berikut termasuk kelompok yang dipilih,
                        tetapi tidak mempunyai parameter DES saat proses
                        peramalan dijalankan.
                    </p>
                </div>

                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200">
                        <thead class="bg-gray-50">
                            <tr>
                                <th class="px-5 py-3 text-left text-xs font-semibold uppercase text-gray-600">Kode</th>
                                <th class="px-5 py-3 text-left text-xs font-semibold uppercase text-gray-600">Nama Produk</th>
                                <th class="px-5 py-3 text-left text-xs font-semibold uppercase text-gray-600">Keterangan</th>
                            </tr>
                        </thead>

                        <tbody class="divide-y divide-gray-100">
                            @foreach ($coverage['skipped_products'] as $product)
                                <tr>
                                    <td class="px-5 py-4 text-sm font-semibold text-gray-900">
                                        {{ $product['kode'] }}
                                    </td>
                                    <td class="px-5 py-4 text-sm text-gray-800">
                                        {{ $product['nama'] }}
                                    </td>
                                    <td class="px-5 py-4 text-sm text-amber-800">
                                        {{ $product['alasan'] }}
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </section>
        @endif
    </div>
</x-layouts.app>
