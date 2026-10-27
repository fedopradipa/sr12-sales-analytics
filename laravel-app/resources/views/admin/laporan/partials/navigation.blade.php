@php
    $reportUser = auth()->user();

    $canSegmentasi = $reportUser?->hasPermission(
        'laporan.segmentasi'
    ) ?? false;

    $canPrediksi = $reportUser?->hasPermission(
        'laporan.prediksi'
    ) ?? false;
@endphp

<div class="border-b border-gray-200">
    <nav
        class="flex flex-wrap gap-6 px-6"
        aria-label="Jenis laporan"
    >
        @if ($canSegmentasi)
            <a
                href="{{ route(
                    'admin.laporan.segmentasi.index'
                ) }}"
                @class([
                    'border-b-2 px-1 py-4 text-sm font-semibold',
                    'border-rose-700 text-rose-800' =>
                        request()->routeIs(
                            'admin.laporan.segmentasi.*'
                        )
                        || request()->routeIs(
                            'admin.laporan.kmeans.*'
                        ),
                    'border-transparent text-gray-500 hover:border-gray-300 hover:text-gray-700' =>
                        ! request()->routeIs(
                            'admin.laporan.segmentasi.*'
                        )
                        && ! request()->routeIs(
                            'admin.laporan.kmeans.*'
                        ),
                ])
            >
                Laporan Segmentasi
            </a>
        @endif

        @if ($canPrediksi)
            <a
                href="{{ route(
                    'admin.laporan.peramalan.index'
                ) }}"
                @class([
                    'border-b-2 px-1 py-4 text-sm font-semibold',
                    'border-rose-700 text-rose-800' =>
                        request()->routeIs(
                            'admin.laporan.peramalan.*'
                        )
                        || request()->routeIs(
                            'admin.laporan.des.*'
                        ),
                    'border-transparent text-gray-500 hover:border-gray-300 hover:text-gray-700' =>
                        ! request()->routeIs(
                            'admin.laporan.peramalan.*'
                        )
                        && ! request()->routeIs(
                            'admin.laporan.des.*'
                        ),
                ])
            >
                Laporan Peramalan
            </a>
        @endif
    </nav>
</div>
