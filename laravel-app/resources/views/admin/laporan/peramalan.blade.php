<x-layouts.app title="Laporan Peramalan">
    <div class="sr12-page space-y-6">
        <section class="rounded-lg bg-white p-6 shadow">
            <h1 class="text-xl font-semibold text-gray-900">
                Laporan Peramalan
            </h1>

            <p class="mt-2 text-sm leading-6 text-gray-600">
                Unduh laporan peramalan penjualan yang dilengkapi
                kondisi persediaan, Stok Perlu Ditambah, cakupan
                produk, dan informasi produk yang tidak dapat
                diramalkan.
            </p>
        </section>

        <section class="overflow-hidden rounded-lg bg-white shadow">
            @include(
                'admin.laporan.partials.navigation'
            )

            <div class="border-b px-6 py-4">
                <h2 class="text-lg font-semibold text-gray-900">
                    Riwayat Peramalan
                </h2>

                <p class="mt-1 text-sm text-gray-600">
                    Pilih hasil berdasarkan segmentasi, kelompok
                    produk, dan periode peramalan.
                </p>
            </div>

            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-200">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="px-5 py-3 text-left text-xs font-semibold uppercase text-gray-600">Peramalan Dibuat</th>
                            <th class="px-5 py-3 text-left text-xs font-semibold uppercase text-gray-600">Hasil Segmentasi</th>
                            <th class="px-5 py-3 text-left text-xs font-semibold uppercase text-gray-600">Kelompok Produk</th>
                            <th class="px-5 py-3 text-left text-xs font-semibold uppercase text-gray-600">Periode Peramalan</th>
                            <th class="px-5 py-3 text-center text-xs font-semibold uppercase text-gray-600">Hasil</th>
                            <th class="px-5 py-3 text-center text-xs font-semibold uppercase text-gray-600">Status</th>
                            <th class="px-5 py-3 text-right text-xs font-semibold uppercase text-gray-600">Aksi</th>
                        </tr>
                    </thead>

                    <tbody class="divide-y divide-gray-200">
                        @forelse ($daftarDes as $proses)
                            @php
                                $coverage = $proses->coverage;

                                $statusClass =
                                    $coverage['status'] === 'partial'
                                        ? 'bg-amber-100 text-amber-800'
                                        : 'bg-green-100 text-green-800';
                            @endphp

                            <tr class="hover:bg-gray-50/70">
                                <td class="whitespace-nowrap px-5 py-4 text-sm">
                                    <p class="font-semibold text-gray-900">
                                        Peramalan #{{ $proses->id }}
                                    </p>
                                    <p class="mt-1 text-xs text-gray-500">
                                        {{ \App\Exports\AnalyticsExportFormatter::processTime($proses->created_at) }}
                                    </p>
                                </td>

                                <td class="px-5 py-4 text-sm">
                                    <p class="font-semibold text-gray-900">
                                        Segmentasi #{{ $proses->kmeans_id }}
                                    </p>
                                    <p class="mt-1 whitespace-nowrap text-xs text-gray-500">
                                        {{ \App\Exports\AnalyticsExportFormatter::dateRange(
                                            $proses->hasilKmeans->awal,
                                            $proses->hasilKmeans->akhir
                                        ) }}
                                    </p>
                                </td>

                                <td class="whitespace-nowrap px-5 py-4 text-sm">
                                    <p class="font-semibold text-gray-900">
                                        {{ \App\Exports\AnalyticsExportFormatter::clusterLabel($proses->cluster) }}
                                    </p>
                                    <p class="mt-1 text-xs text-gray-500">
                                        Cluster {{ $proses->cluster }}
                                    </p>
                                </td>

                                <td class="whitespace-nowrap px-5 py-4 text-sm font-semibold text-gray-900">
                                    {{ $proses->target->locale('id')->translatedFormat('F Y') }}
                                </td>

                                <td class="px-5 py-4 text-center text-sm font-semibold text-gray-900">
                                    {{ $coverage['forecasted'] }}
                                    /
                                    {{ $coverage['total'] }}
                                </td>

                                <td class="px-5 py-4 text-center">
                                    <span class="inline-flex rounded-full px-3 py-1 text-xs font-semibold {{ $statusClass }}">
                                        {{ $coverage['status_label'] }}
                                    </span>
                                </td>

                                <td class="whitespace-nowrap px-5 py-4 text-right">
                                    <x-ui.button
                                        href="{{ route('admin.laporan.des.batch.pdf', $proses) }}"
                                        variant="secondary"
                                        size="small"
                                    >
                                        Unduh PDF
                                    </x-ui.button>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="7" class="px-6 py-10 text-center text-sm text-gray-500">
                                    Belum ada hasil peramalan yang dapat diunduh.
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            @if ($daftarDes->hasPages())
                <div class="border-t px-6 py-4">
                    {{ $daftarDes->links() }}
                </div>
            @endif
        </section>
    </div>
</x-layouts.app>
