<x-layouts.auth title="Login">
    <div class="sr12-login-card">
        <section class="sr12-login-visual">
            <div class="sr12-login-orb" aria-hidden="true"></div>

            <div class="sr12-login-visual-content">
                <div class="sr12-login-logo-wrap">
                    <img
                        src="{{ asset('images/logo-sr12.png') }}"
                        alt="Logo SR12"
                        class="sr12-login-logo"
                    >
                </div>

                <p class="sr12-login-eyebrow">
                    Sistem Analitik Penjualan
                </p>

                <h1>Distributor SR12</h1>

                <p class="sr12-login-description">
                    Sistem analisis penjualan untuk
                    segmentasi produk dan peramalan penjualan.
                </p>

                <ul class="sr12-login-features">
                    <li>
                        <span>✓</span>
                        Segmentasi Produk K-Means
                    </li>

                    <li>
                        <span>✓</span>
                        Peramalan Penjualan DES Brown
                    </li>

                    <li>
                        <span>✓</span>
                        Laporan Analitik PDF
                    </li>
                </ul>

                <p class="sr12-login-copyright">
                    © {{ now()->year }} Distributor SR12
                </p>
            </div>
        </section>

        <section class="sr12-login-form-panel">
            <div class="sr12-login-form">
                <div class="sr12-login-heading">
                    <h2>Login</h2>

                    <p>
                        Masuk ke akun Anda untuk melanjutkan.
                    </p>
                </div>

                @if (session('success'))
                    <div
                        class="mb-5 rounded-xl border border-green-200 bg-green-50 px-4 py-3 text-sm text-green-700"
                        role="status"
                    >
                        {{ session('success') }}
                    </div>
                @endif

                <form
                    action="{{ route('login.store') }}"
                    method="POST"
                    class="space-y-5"
                >
                    @csrf

                    <x-forms.input
                        label="Email"
                        name="email"
                        type="email"
                        :value="old('email')"
                        placeholder="nama@email.com"
                        autocomplete="email"
                        autofocus
                    />

                    <x-forms.input
                        label="Password"
                        name="password"
                        type="password"
                        placeholder="Masukkan password"
                        autocomplete="current-password"
                    />



                    <x-ui.button full>
                        Masuk
                    </x-ui.button>
                </form>

                <p class="sr12-login-footer">
                    Distributor SR12 · Sistem Analitik Penjualan
                </p>
            </div>
        </section>
    </div>
</x-layouts.auth>
