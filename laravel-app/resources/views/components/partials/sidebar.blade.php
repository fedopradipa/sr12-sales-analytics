@props(['role' => null])

@php
    $user = auth()->user();
    $menus = [];

    $hasDashboard = $user !== null
        && app(\App\Services\DashboardService::class)
            ->canAccess($user);

    if ($hasDashboard) {
        $menus[] = [
            'group' => 'Ringkasan',
            'label' => 'Dashboard',
            'route' => 'admin.dashboard',
            'active' => 'admin.dashboard',
            'icon' => 'dashboard',
        ];
    }

    foreach ([
        ['produk', 'Master Data', 'Produk', 'admin.produk.index', 'admin.produk.*', 'product'],
        ['transaksi', 'Operasional', 'Transaksi Penjualan', 'admin.transaksi.index', 'admin.transaksi.*', 'transaction'],
        ['kmeans', 'Analitik', 'Segmentasi Produk', 'admin.kmeans.index', 'admin.kmeans.*', 'cluster'],
        ['kalibrasi', 'Analitik', 'Penentuan Parameter', 'admin.kalibrasi-parameter.index', 'admin.kalibrasi-parameter.*', 'forecast'],
        ['des', 'Analitik', 'Peramalan Penjualan', 'admin.des.index', 'admin.des.*', 'forecast'],
    ] as [$permission, $group, $label, $route, $active, $icon]) {
        if ($user?->hasPermission($permission)) {
            $menus[] = compact('group', 'label', 'route', 'active', 'icon');
        }
    }

    $hasLaporanSegmentasi = $user?->hasPermission(
        'laporan.segmentasi'
    ) ?? false;

    $hasLaporanPrediksi = $user?->hasPermission(
        'laporan.prediksi'
    ) ?? false;

    if (
        $hasLaporanSegmentasi
        || $hasLaporanPrediksi
    ) {
        $menus[] = [
            'group' => 'Pelaporan',
            'label' => 'Laporan',
            'route' => $hasLaporanSegmentasi
                ? 'admin.laporan.segmentasi.index'
                : 'admin.laporan.peramalan.index',
            'active' => 'admin.laporan.*',
            'icon' => 'report',
        ];
    }

    if ($user?->isAdmin()) {
        $menus[] = [
            'group' => 'Administrasi',
            'label' => 'Pengguna',
            'route' => 'admin.users.index',
            'active' => 'admin.users.*',
            'icon' => 'users',
        ];
    }

    $initials = collect(preg_split('/\s+/', trim($user?->name ?? 'SR12')))
        ->filter()
        ->take(2)
        ->map(fn ($part) => mb_strtoupper(mb_substr($part, 0, 1)))
        ->implode('');

    $currentGroup = null;
@endphp

<aside id="sr12-sidebar" class="sr12-sidebar" aria-label="Navigasi utama">
    <div class="sr12-brand">
        <div class="sr12-brand-logo">
            <img
                src="{{ asset('images/logo-sr12.png') }}"
                alt="Logo SR12"
            >
        </div>

        <div class="sr12-brand-copy">
            <strong>DISTRIBUTOR SR12</strong>
        </div>

        <button
            type="button"
            class="sr12-sidebar-toggle"
            aria-label="Tutup navigasi"
            aria-controls="sr12-sidebar"
            title="Tutup sidebar"
            data-sidebar-close
        >
            <svg
                viewBox="0 0 24 24"
                fill="none"
                stroke="currentColor"
                stroke-width="2"
                aria-hidden="true"
            >
                <path d="M4 7h16M4 12h16M4 17h16" />
            </svg>
        </button>
    </div>

    <nav class="sr12-nav">
        @foreach ($menus as $menu)
            @if ($currentGroup !== $menu['group'])
                @php($currentGroup = $menu['group'])
                <p class="sr12-nav-group">{{ $currentGroup }}</p>
            @endif

            @php($isActive = request()->routeIs($menu['active']))

            <a
                href="{{ route($menu['route']) }}"
                @class([
                    'sr12-nav-item',
                    'is-active' => $isActive,
                ])
                @if ($isActive) aria-current="page" @endif
            >
                <span class="sr12-nav-icon" aria-hidden="true">
                    @switch($menu['icon'])
                        @case('dashboard')
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M4 4h6v6H4zM14 4h6v6h-6zM4 14h6v6H4zM14 14h6v6h-6z"/></svg>
                            @break                        @case('product')
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="m12 3 8 4.5v9L12 21l-8-4.5v-9L12 3Z"/><path d="m4.5 7.8 7.5 4.1 7.5-4.1M12 12v9"/></svg>
                            @break
                        @case('transaction')
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M6 3h12v18l-3-2-3 2-3-2-3 2V3Z"/><path d="M9 8h6M9 12h6M9 16h4"/></svg>
                            @break
                        @case('cluster')
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><circle cx="7" cy="7" r="3"/><circle cx="17" cy="7" r="3"/><circle cx="7" cy="17" r="3"/><circle cx="17" cy="17" r="3"/><path d="m9.4 9.4 5.2 5.2M14.6 9.4l-5.2 5.2"/></svg>
                            @break
                        @case('forecast')
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M3 18c3-7 5 1 8-5s5 2 10-6"/><path d="M17 7h4v4"/></svg>
                            @break
                        @case('report')
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M5 3h11l3 3v15H5V3Z"/><path d="M8 9h8M8 13h8M8 17h5"/></svg>
                            @break
                        @default
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><circle cx="9" cy="8" r="4"/><path d="M3 21c0-4 2-7 6-7s6 3 6 7M16 5a3 3 0 1 1 0 6M16 14c3 0 5 2 5 6"/></svg>
                    @endswitch
                </span>

                <span>{{ $menu['label'] }}</span>
            </a>
        @endforeach
    </nav>

    <div class="sr12-sidebar-user">
        <div
            class="sr12-user-avatar"
            aria-hidden="true"
        >
            {{ $initials ?: 'S12' }}
        </div>

        <div class="sr12-user-copy">
            <strong>{{ $user?->name }}</strong>
            <small>{{ $user?->role }}</small>
        </div>

        <form
            action="{{ route('logout') }}"
            method="POST"
            class="sr12-sidebar-logout-form"
        >
            @csrf

            <button
                type="submit"
                class="sr12-sidebar-logout"
                aria-label="Logout"
                title="Logout"
            >
                <svg
                    viewBox="0 0 24 24"
                    fill="none"
                    stroke="currentColor"
                    stroke-width="1.9"
                    aria-hidden="true"
                >
                    <path d="M10 5H6a2 2 0 0 0-2 2v10a2 2 0 0 0 2 2h4" />
                    <path d="m15 8 4 4-4 4" />
                    <path d="M19 12H9" />
                </svg>
            </button>
        </form>
    </div>
</aside>
