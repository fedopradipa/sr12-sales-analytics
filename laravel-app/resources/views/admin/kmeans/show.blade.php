<x-layouts.app title="Hasil Segmentasi Produk">
    @php
        $clusterLabels = [
            1 => 'Pergerakan Tinggi',
            2 => 'Pergerakan Sedang',
            3 => 'Pergerakan Rendah',
        ];

        $hasSearch =
            trim((string) request('search')) !== '';
    @endphp

    <div class="sr12-page space-y-6">
        @if (session('success'))
            <div
                class="rounded border border-green-200 bg-green-50 px-4 py-3 text-sm text-green-800"
                role="alert"
            >
                {{ session('success') }}
            </div>
        @endif

        <div class="flex flex-wrap items-center justify-between gap-4">
            <div>
                <h1 class="text-xl font-semibold text-gray-900">
                    Hasil Segmentasi Produk
                </h1>

                <p class="mt-1 text-sm text-gray-600">
                    Periode analisis
                    {{ $hasilKmeans->awal->format('d/m/Y') }}
                    s.d.
                    {{ $hasilKmeans->akhir->format('d/m/Y') }}
                </p>
            </div>

            <a
                href="{{ route('admin.kmeans.index') }}"
                class="rounded border border-gray-300 bg-white px-4 py-2 text-sm font-semibold text-gray-700 hover:bg-gray-50"
            >
                Kembali
            </a>
        </div>

        <section class="rounded-lg bg-white p-5 shadow">
            <form
                method="GET"
                action="{{
                    route(
                        'admin.kmeans.show',
                        $hasilKmeans
                    )
                }}"
                class="flex flex-wrap items-end gap-3"
            >
                <input
                    type="hidden"
                    name="cluster"
                    value="{{ $openCluster }}"
                    data-open-cluster-input
                >

                <div class="min-w-0 flex-1">
                    <label
                        for="search"
                        class="mb-2 block text-sm font-medium text-gray-700"
                    >
                        Cari Produk
                    </label>

                    <input
                        id="search"
                        name="search"
                        type="search"
                        value="{{ request('search') }}"
                        placeholder="Masukkan kode atau nama produk"
                        class="w-full rounded border border-gray-300 px-3 py-2 text-sm"
                    >
                </div>

                <div>
                    <label
                        for="per_page"
                        class="mb-2 block text-sm font-medium text-gray-700"
                    >
                        Baris per Kelompok
                    </label>

                    <select
                        id="per_page"
                        name="per_page"
                        class="rounded border border-gray-300 px-3 py-2 text-sm"
                    >
                        @foreach ([10, 25, 50, 100] as $size)
                            <option
                                value="{{ $size }}"
                                @selected(
                                    (int) request(
                                        'per_page',
                                        25
                                    ) === $size
                                )
                            >
                                {{ $size }}
                            </option>
                        @endforeach
                    </select>
                </div>

                <button
                    type="submit"
                    class="rounded bg-blue-600 px-4 py-2 text-sm font-semibold text-white hover:bg-blue-700"
                >
                    Terapkan
                </button>

                @if (
                    request()->filled('search')
                    || request()->filled('per_page')
                )
                    <a
                        href="{{
                            route(
                                'admin.kmeans.show',
                                [
                                    'hasilKmeans' =>
                                        $hasilKmeans,
                                    'cluster' =>
                                        $openCluster,
                                ]
                            )
                        }}"
                        class="rounded border border-gray-300 bg-white px-4 py-2 text-sm font-semibold text-gray-700 hover:bg-gray-50"
                    >
                        Reset
                    </a>
                @endif
            </form>

            @if ($hasSearch)
                <p class="mt-3 text-xs leading-5 text-gray-500">
                    Semua kelompok prioritas dibuka agar hasil pencarian
                    dapat langsung dibandingkan.
                </p>
            @endif
        </section>

        <div class="space-y-4">
            @foreach ([1, 2, 3] as $clusterNumber)
                @php
                    $clusterDetails =
                        $clusterDetailsByCluster[
                            $clusterNumber
                        ];

                    $shouldOpen =
                        $hasSearch
                        || $openCluster
                            === $clusterNumber;
                @endphp

                <details
                    id="cluster-{{ $clusterNumber }}"
                    data-cluster-panel="{{ $clusterNumber }}"
                    @if ($shouldOpen) open @endif
                    class="scroll-mt-24 overflow-hidden rounded-lg bg-white shadow"
                >
                    <summary
                        class="flex cursor-pointer list-none items-center justify-between gap-4 border-b px-6 py-4 transition hover:bg-gray-50 [&::-webkit-details-marker]:hidden"
                    >
                        <div class="flex min-w-0 items-center gap-4">
                            <span
                                class="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-blue-50 text-sm font-bold text-blue-700"
                                aria-hidden="true"
                            >
                                {{ $clusterNumber }}
                            </span>

                            <div class="min-w-0">
                                <h2 class="text-base font-semibold text-gray-900">
                                    Cluster {{ $clusterNumber }}
                                </h2>

                                <p class="mt-1 text-sm text-gray-600">
                                    {{
                                        $clusterLabels[
                                            $clusterNumber
                                        ]
                                    }}
                                </p>
                            </div>
                        </div>

                        <div class="flex shrink-0 items-center gap-3">
                            <span class="rounded-full bg-gray-100 px-3 py-1 text-sm font-medium text-gray-700">
                                @if ($hasSearch)
                                    {{ $clusterDetails->total() }}
                                    hasil
                                @else
                                    {{
                                        $clusterCounts[
                                            $clusterNumber
                                        ]
                                    }}
                                    produk
                                @endif
                            </span>

                            <svg
                                class="h-5 w-5 text-gray-500 transition group-open:rotate-180"
                                viewBox="0 0 24 24"
                                fill="none"
                                stroke="currentColor"
                                stroke-width="2"
                                aria-hidden="true"
                            >
                                <path
                                    d="m6 9 6 6 6-6"
                                    stroke-linecap="round"
                                    stroke-linejoin="round"
                                />
                            </svg>
                        </div>
                    </summary>

                    <div class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-gray-200">
                            <thead class="bg-gray-50">
                                <tr>
                                    <th class="px-6 py-3 text-left text-xs font-semibold uppercase text-gray-600">
                                        Produk
                                    </th>

                                    <th class="px-6 py-3 text-right text-xs font-semibold uppercase text-gray-600">
                                        Recency (Hari)
                                    </th>

                                    <th class="px-6 py-3 text-right text-xs font-semibold uppercase text-gray-600">
                                        Jumlah Hari Terjual
                                    </th>

                                    <th class="px-6 py-3 text-right text-xs font-semibold uppercase text-gray-600">
                                        Total Terjual
                                    </th>

                                    <th class="px-6 py-3 text-right text-xs font-semibold uppercase text-gray-600">
                                        Total Omzet
                                    </th>
                                </tr>
                            </thead>

                            <tbody class="divide-y divide-gray-200">
                                @forelse (
                                    $clusterDetails
                                    as $detail
                                )
                                    <tr>
                                        <td class="px-6 py-4">
                                            <p class="text-sm font-semibold text-gray-900">
                                                {{
                                                    $detail->produk?->nama
                                                    ?? 'Produk dihapus'
                                                }}
                                            </p>

                                            <p class="mt-1 text-xs text-gray-500">
                                                {{
                                                    $detail->produk?->kode
                                                    ?? '-'
                                                }}
                                            </p>
                                        </td>

                                        <td class="px-6 py-4 text-right text-sm text-gray-700">
                                            {{
                                                number_format(
                                                    $detail->recency,
                                                    0,
                                                    ',',
                                                    '.'
                                                )
                                            }}
                                            hari
                                        </td>

                                        <td class="px-6 py-4 text-right text-sm text-gray-700">
                                            {{
                                                number_format(
                                                    $detail->frekuensi,
                                                    0,
                                                    ',',
                                                    '.'
                                                )
                                            }}
                                            hari
                                        </td>

                                        <td class="px-6 py-4 text-right text-sm text-gray-700">
                                            {{
                                                number_format(
                                                    $detail->qty,
                                                    0,
                                                    ',',
                                                    '.'
                                                )
                                            }}
                                        </td>

                                        <td class="px-6 py-4 text-right text-sm font-medium text-gray-900">
                                            Rp
                                            {{
                                                number_format(
                                                    $detail->omzet,
                                                    0,
                                                    ',',
                                                    '.'
                                                )
                                            }}
                                        </td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td
                                            colspan="5"
                                            class="px-6 py-10 text-center text-sm text-gray-500"
                                        >
                                            @if ($hasSearch)
                                                Tidak ada produk pada
                                                kelompok ini yang sesuai
                                                dengan pencarian.
                                            @else
                                                Belum ada produk pada
                                                kelompok ini.
                                            @endif
                                        </td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>

                    @if ($clusterDetails->hasPages())
                        <div class="border-t px-6 py-4">
                            {{ $clusterDetails->links() }}
                        </div>
                    @endif
                </details>
            @endforeach
        </div>
    </div>

</x-layouts.app>
