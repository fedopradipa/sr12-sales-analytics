@php
    $hasAvailablePeriod =
        filled($periodeTersedia['awal'] ?? null)
        && filled($periodeTersedia['akhir'] ?? null);
@endphp

<x-layouts.app title="Segmentasi Produk">
    <div class="sr12-page space-y-6">
        @if (session('success'))
            <div
                class="rounded border border-green-200 bg-green-50 px-4 py-3 text-sm text-green-800"
                role="status"
            >
                {{ session('success') }}
            </div>
        @endif

        <section class="rounded-lg bg-white p-6 shadow">
            <div class="flex flex-wrap items-center justify-between gap-4">
                <div>
                    <h1 class="text-xl font-semibold text-gray-900">
                        Segmentasi Produk
                    </h1>

                    <p class="mt-2 max-w-3xl text-sm leading-6 text-gray-600">
                        Segmentasi menggunakan Recency
                        (jarak hari sejak penjualan terakhir),
                        frekuensi hari penjualan, total qty,
                        dan total omzet pada periode yang dipilih.
                    </p>
                </div>

                @if ($hasAvailablePeriod)
                    <x-ui.button
                        type="button"
                        variant="primary"
                        data-kmeans-modal-open="kmeans-form-modal"
                    >
                        Buat Segmentasi
                    </x-ui.button>
                @else
                    <x-ui.button
                        type="button"
                        variant="primary"
                        disabled
                        class="cursor-not-allowed opacity-50"
                        aria-disabled="true"
                        title="Belum ada transaksi yang dapat dianalisis"
                    >
                        Buat Segmentasi
                    </x-ui.button>
                @endif
            </div>

            @if ($hasAvailablePeriod)
                <div class="mt-4 rounded bg-blue-50 px-4 py-3 text-sm text-blue-800">
                    Data transaksi tersedia dari
                    <strong>
                        {{
                            \Illuminate\Support\Carbon::parse(
                                $periodeTersedia['awal']
                            )->translatedFormat('d F Y')
                        }}
                    </strong>
                    sampai
                    <strong>
                        {{
                            \Illuminate\Support\Carbon::parse(
                                $periodeTersedia['akhir']
                            )->translatedFormat('d F Y')
                        }}
                    </strong>.
                </div>
            @else
                <div
                    class="mt-4 rounded border border-yellow-200 bg-yellow-50 px-4 py-3 text-sm text-yellow-800"
                    role="status"
                >
                    Belum ada transaksi yang dapat dianalisis.
                </div>
            @endif
        </section>

        <section class="overflow-hidden rounded-lg bg-white shadow">
            <div class="border-b px-6 py-4">
                <h2 class="text-lg font-semibold text-gray-900">
                    Riwayat Segmentasi
                </h2>
            </div>

            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-200">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="px-6 py-3 text-left text-xs font-semibold uppercase text-gray-600">
                                Periode
                            </th>

                            <th class="px-6 py-3 text-center text-xs font-semibold uppercase text-gray-600">
                                Produk
                            </th>

                            <th class="px-6 py-3 text-left text-xs font-semibold uppercase text-gray-600">
                                Proses
                            </th>

                            <th class="px-6 py-3 text-center text-xs font-semibold uppercase text-gray-600">
                                Iterasi
                            </th>

                            <th class="px-6 py-3 text-right text-xs font-semibold uppercase text-gray-600">
                                Aksi
                            </th>
                        </tr>
                    </thead>

                    <tbody class="divide-y divide-gray-200">
                        @forelse ($daftarHasil as $hasil)
                            <tr>
                                <td class="whitespace-nowrap px-6 py-4 text-sm text-gray-700">
                                    {{
                                        $hasil->awal
                                            ->format('d/m/Y')
                                    }}
                                    –
                                    {{
                                        $hasil->akhir
                                            ->format('d/m/Y')
                                    }}
                                </td>

                                <td class="px-6 py-4 text-center text-sm text-gray-700">
                                    {{
                                        number_format(
                                            $hasil->detail_count,
                                            0,
                                            ',',
                                            '.'
                                        )
                                    }}
                                </td>

                                <td class="px-6 py-4 text-sm text-gray-700">
                                    <p class="font-medium text-gray-900">
                                        {{ $hasil->user?->name ?? '-' }}
                                    </p>

                                    <p class="mt-1 text-xs text-gray-500">
                                        {{
                                            $hasil->created_at
                                                ?->format('d/m/Y H:i')
                                        }}
                                    </p>
                                </td>

                                <td class="px-6 py-4 text-center text-sm text-gray-700">
                                    {{ $hasil->iterasi }}
                                </td>

                                <td class="px-6 py-4 text-right">
                                    <x-ui.button
                                        href="{{ route('admin.kmeans.show', $hasil) }}"
                                        variant="secondary"
                                        size="small"
                                    >
                                        Lihat Hasil
                                    </x-ui.button>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td
                                    colspan="5"
                                    class="px-6 py-10 text-center text-sm text-gray-500"
                                >
                                    Belum ada hasil segmentasi produk.
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            @if ($daftarHasil->hasPages())
                <div class="border-t px-6 py-4">
                    {{ $daftarHasil->links() }}
                </div>
            @endif
        </section>

        @include('admin.kmeans.partials.form-modal')
    </div>
</x-layouts.app>
