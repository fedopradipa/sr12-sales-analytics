<x-layouts.app title="Peramalan Penjualan">
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
            <div class="flex flex-wrap items-center justify-between gap-4">
                <div>
                    <h1 class="text-xl font-semibold text-gray-900">
                        Peramalan Penjualan
                    </h1>

                    <p class="mt-2 text-sm leading-6 text-gray-600">
                        Riwayat peramalan satu bulan berikutnya
                        berdasarkan kelompok hasil segmentasi produk.
                    </p>
                </div>

                @if (
                    $selectionOptions === []
                    || $predictionContext === null
                )
                    <x-ui.button
                        type="button"
                        variant="primary"
                        disabled
                        class="cursor-not-allowed opacity-50"
                        aria-disabled="true"
                        title="Hasil segmentasi atau data penjualan belum tersedia"
                    >
                        Buat Peramalan
                    </x-ui.button>
                @else
                    <x-ui.button
                        type="button"
                        variant="primary"
                        data-des-modal-open="des-form-modal"
                    >
                        Buat Peramalan
                    </x-ui.button>
                @endif
            </div>
        </section>

        <section class="overflow-hidden rounded-lg bg-white shadow">
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-200">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="px-5 py-3 text-left text-xs font-semibold uppercase text-gray-600">Waktu Proses</th>
                            <th class="px-5 py-3 text-left text-xs font-semibold uppercase text-gray-600">Sumber Segmentasi</th>
                            <th class="px-5 py-3 text-left text-xs font-semibold uppercase text-gray-600">Kelompok Produk</th>
                            <th class="px-5 py-3 text-left text-xs font-semibold uppercase text-gray-600">Periode Prediksi</th>
                            <th class="px-5 py-3 text-center text-xs font-semibold uppercase text-gray-600">Hasil</th>
                            <th class="px-5 py-3 text-center text-xs font-semibold uppercase text-gray-600">Status</th>
                            <th class="px-5 py-3 text-right text-xs font-semibold uppercase text-gray-600">Aksi</th>
                        </tr>
                    </thead>

                    <tbody class="divide-y divide-gray-200">
                        @forelse ($daftarProses as $proses)
                            @php
                                $processTime = $proses->created_at
                                    ?->copy()
                                    ->setTimezone('Asia/Jakarta')
                                    ->locale('id');

                                $coverage = $proses->coverage;

                                $statusClass =
                                    $coverage['status'] === 'partial'
                                        ? 'bg-amber-100 text-amber-800'
                                        : 'bg-green-100 text-green-800';
                            @endphp

                            <tr>
                                <td class="whitespace-nowrap px-5 py-4 text-sm text-gray-700">
                                    <p class="font-semibold text-gray-900">
                                        {{ $processTime?->translatedFormat('d F Y, H.i') ?? '-' }}
                                    </p>
                                    <p class="mt-1 text-xs text-gray-500">WIB</p>
                                </td>

                                <td class="px-5 py-4 text-sm text-gray-700">
                                    <p class="font-semibold text-gray-900">
                                        Segmentasi #{{ $proses->kmeans_id }}
                                    </p>
                                    <p class="mt-1 whitespace-nowrap text-xs text-gray-500">
                                        {{ ($proses->hasilKmeans?->awal ?? $proses->data_awal)?->format('d/m/Y') ?? '-' }}
                                        s.d.
                                        {{ ($proses->hasilKmeans?->akhir ?? $proses->data_akhir)?->format('d/m/Y') ?? '-' }}
                                    </p>
                                </td>

                                <td class="px-5 py-4 text-sm text-gray-700">
                                    <p class="font-semibold text-gray-900">
                                        {{ \App\Services\DesViewDataService::CLUSTER_LABELS[$proses->cluster] ?? '-' }}
                                    </p>
                                    <p class="mt-1 text-xs text-gray-500">
                                        Cluster {{ $proses->cluster }}
                                    </p>
                                </td>

                                <td class="whitespace-nowrap px-5 py-4 text-sm font-semibold text-gray-900">
                                    {{ $proses->target->locale('id')->translatedFormat('F Y') }}
                                </td>

                                <td class="px-5 py-4 text-center text-sm font-semibold text-gray-900">
                                    {{ number_format($coverage['forecasted'], 0, ',', '.') }}
                                    /
                                    {{ number_format($coverage['total'], 0, ',', '.') }}
                                </td>

                                <td class="px-5 py-4 text-center">
                                    <span class="inline-flex rounded-full px-3 py-1 text-xs font-semibold {{ $statusClass }}">
                                        {{ $coverage['status_label'] }}
                                    </span>
                                </td>

                                <td class="px-5 py-4 text-right">
                                    <x-ui.button
                                        href="{{ route('admin.des.process.show', $proses) }}"
                                        variant="secondary"
                                        size="small"
                                    >
                                        Lihat Hasil
                                    </x-ui.button>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="7" class="px-6 py-10 text-center text-sm text-gray-500">
                                    Belum ada peramalan penjualan.
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            @if ($daftarProses->hasPages())
                <div class="border-t px-6 py-4">
                    {{ $daftarProses->links() }}
                </div>
            @endif
        </section>

        @include('admin.des.partials.form-modal')
    </div>
</x-layouts.app>
