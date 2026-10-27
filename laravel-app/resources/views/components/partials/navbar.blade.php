@props([
    'title' => 'Dashboard',
    'role' => 'admin',
])

<header class="sr12-topbar">
    <div class="sr12-topbar-left">
        <button
            type="button"
            class="sr12-menu-toggle"
            aria-label="Buka navigasi"
            aria-controls="sr12-sidebar"
            aria-expanded="true"
            data-sidebar-toggle
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

        <div class="sr12-topbar-title">
            <small>Sistem Analitik Penjualan</small>
            <h2>{{ $title }}</h2>
        </div>
    </div>
</header>
