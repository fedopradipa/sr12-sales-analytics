<x-layouts.app title="Laporan Segmentasi">
    <div class="sr12-page space-y-6">
        <section class="rounded-lg bg-white p-6 shadow">
            <h1 class="text-xl font-semibold text-gray-900">
                Laporan Segmentasi
            </h1>

            <p class="mt-2 text-sm leading-6 text-gray-600">
                Unduh laporan segmentasi produk yang
                dilengkapi informasi proses, periode analisis,
                kelompok prioritas dan nilai atribut produk.
            </p>
        </section>

        <section class="overflow-hidden rounded-lg bg-white shadow">
            @include(
                'admin.laporan.partials.navigation'
            )

            <div class="border-b px-6 py-4">
                <h2 class="text-lg font-semibold text-gray-900">
                    Riwayat Segmentasi
                </h2>

                <p class="mt-1 text-sm text-gray-600">
                    Nomor segmentasi membedakan hasil yang
                    memiliki periode sama.
                </p>
            </div>

            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-200">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="px-5 py-3 text-left text-xs font-semibold uppercase text-gray-600">
                                Hasil Segmentasi
                            </th>

                            <th class="px-5 py-3 text-center text-xs font-semibold uppercase text-gray-600">
                                Jumlah Produk
                            </th>

                            <th class="px-5 py-3 text-left text-xs font-semibold uppercase text-gray-600">
                                Waktu Proses
                            </th>

                            <th class="px-5 py-3 text-right text-xs font-semibold uppercase text-gray-600">
                                Aksi
                            </th>
                        </tr>
                    </thead>

                    <tbody class="divide-y divide-gray-200">
                        @forelse ($daftarKmeans as $hasil)
                            <tr>
                                <td class="px-5 py-4 text-sm text-gray-700">
                                    <p class="font-semibold text-gray-900">
                                        Segmentasi #{{ $hasil->id }}
                                    </p>

                                    <p class="mt-1 text-xs text-gray-500">
                                        {{
                                            \App\Exports\AnalyticsExportFormatter::dateRange(
                                                $hasil->awal,
                                                $hasil->akhir
                                            )
                                        }}
                                    </p>
                                </td>

                                <td class="px-5 py-4 text-center text-sm font-semibold text-gray-900">
                                    {{
                                        number_format(
                                            $hasil->detail_count,
                                            0,
                                            ',',
                                            '.'
                                        )
                                    }}
                                </td>

                                <td class="px-5 py-4 text-sm text-gray-700">
                                    <p class="font-medium text-gray-900">
                                        {{
                                            \App\Exports\AnalyticsExportFormatter::processTime(
                                                $hasil->created_at
                                            )
                                        }}
                                    </p>

                                    <p class="mt-1 text-xs text-gray-500">
                                        {{ $hasil->user?->name ?? '-' }}
                                    </p>
                                </td>

                                <td class="px-5 py-4 text-right">
                                    <a
                                        href="{{ route(
                                            'admin.laporan.kmeans.pdf',
                                            $hasil
                                        ) }}"
                                        class="text-sm font-semibold text-blue-600 hover:text-blue-800"
                                    >
                                        Unduh PDF
                                    </a>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td
                                    colspan="4"
                                    class="px-6 py-10 text-center text-sm text-gray-500"
                                >
                                    Belum ada hasil segmentasi yang
                                    dapat dicetak.
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            @if ($daftarKmeans->hasPages())
                <div class="border-t px-6 py-4">
                    {{ $daftarKmeans->links() }}
                </div>
            @endif
        </section>
    </div>
</x-layouts.app>
