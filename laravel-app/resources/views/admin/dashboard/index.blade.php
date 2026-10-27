<x-layouts.app title="Dashboard">
    <section class="sr12-page space-y-6">
        <header class="sr12-dashboard-hero">
            <div>
                <small>RINGKASAN</small>

                <h1>Dashboard</h1>

                <p>
                    Ringkasan data sesuai fitur yang dapat
                    Anda akses.
                </p>
            </div>
        </header>

        <div
            class="grid grid-cols-1 gap-4 sm:grid-cols-2 xl:grid-cols-3"
        >
            @if ($cards['produk'])
                <article
                    class="sr12-metric-card"
                    style="--metric-color:#b76f75;--metric-soft:#f9e5e8"
                >
                    <div class="sr12-metric-icon">
                        <svg
                            viewBox="0 0 24 24"
                            fill="none"
                            stroke="currentColor"
                            stroke-width="1.8"
                            aria-hidden="true"
                        >
                            <path
                                d="m12 3 8 4.5v9L12 21l-8-4.5v-9L12 3Z"
                            />
                            <path
                                d="m4.5 7.8 7.5 4.1 7.5-4.1M12 12v9"
                            />
                        </svg>
                    </div>

                    <p>Jumlah Produk</p>

                    <strong>
                        {{ number_format(
                            $jumlahProduk,
                            0,
                            ',',
                            '.'
                        ) }}
                    </strong>

                    <small>
                        Produk aktif yang tersimpan.
                    </small>
                </article>
            @endif

            @if ($cards['transaksi'])
                <article
                    class="sr12-metric-card"
                    style="--metric-color:#8d4d52;--metric-soft:#f2d7dc"
                >
                    <div class="sr12-metric-icon">
                        <svg
                            viewBox="0 0 24 24"
                            fill="none"
                            stroke="currentColor"
                            stroke-width="1.8"
                            aria-hidden="true"
                        >
                            <path
                                d="M6 3h12v18l-3-2-3 2-3-2-3 2V3Z"
                            />
                            <path
                                d="M9 8h6M9 12h6M9 16h4"
                            />
                        </svg>
                    </div>

                    <p>Total Produk Terjual</p>

                    <strong>
                        {{ number_format(
                            $totalProdukTerjual,
                            0,
                            ',',
                            '.'
                        ) }} Pcs
                    </strong>

                    <small>
                        Akumulasi kuantitas produk terjual.
                    </small>
                </article>
            @endif

            @if ($cards['segmentasi'])
                <article
                    class="sr12-metric-card"
                    style="--metric-color:#a96870;--metric-soft:#f7e2e5"
                >
                    <div class="sr12-metric-icon">
                        <svg
                            viewBox="0 0 24 24"
                            fill="none"
                            stroke="currentColor"
                            stroke-width="1.8"
                            aria-hidden="true"
                        >
                            <circle cx="7" cy="7" r="3" />
                            <circle cx="17" cy="7" r="3" />
                            <circle cx="7" cy="17" r="3" />
                            <circle cx="17" cy="17" r="3" />
                            <path
                                d="m9.4 9.4 5.2 5.2M14.6 9.4l-5.2 5.2"
                            />
                        </svg>
                    </div>

                    <p>Jumlah Segmentasi</p>

                    <strong>
                        {{ number_format(
                            $jumlahSegmentasi,
                            0,
                            ',',
                            '.'
                        ) }}
                    </strong>

                    <small>
                        Proses segmentasi yang tersimpan.
                    </small>
                </article>
            @endif

            @if ($cards['prediksi'])
                <article
                    class="sr12-metric-card"
                    style="--metric-color:#96545c;--metric-soft:#f3dade"
                >
                    <div class="sr12-metric-icon">
                        <svg
                            viewBox="0 0 24 24"
                            fill="none"
                            stroke="currentColor"
                            stroke-width="1.8"
                            aria-hidden="true"
                        >
                            <path
                                d="M3 18c3-7 5 1 8-5s5 2 10-6"
                            />
                            <path d="M17 7h4v4" />
                        </svg>
                    </div>

                    <p>Jumlah Prediksi</p>

                    <strong>
                        {{ number_format(
                            $jumlahPrediksi,
                            0,
                            ',',
                            '.'
                        ) }}
                    </strong>

                    <small>
                        Proses prediksi yang tersimpan.
                    </small>
                </article>
            @endif

            @if ($cards['pengguna'])
                <article
                    class="sr12-metric-card"
                    style="--metric-color:#71363d;--metric-soft:#efd2d7"
                >
                    <div class="sr12-metric-icon">
                        <svg
                            viewBox="0 0 24 24"
                            fill="none"
                            stroke="currentColor"
                            stroke-width="1.8"
                            aria-hidden="true"
                        >
                            <circle cx="9" cy="8" r="4" />
                            <path
                                d="M3 21c0-4 2-7 6-7s6 3 6 7"
                            />
                            <path
                                d="M16 5a3 3 0 1 1 0 6"
                            />
                            <path
                                d="M16 14c3 0 5 2 5 6"
                            />
                        </svg>
                    </div>

                    <p>Jumlah Pengguna</p>

                    <strong>
                        {{ number_format(
                            $jumlahPengguna,
                            0,
                            ',',
                            '.'
                        ) }}
                    </strong>

                    <small>
                        Akun pengguna aktif.
                    </small>
                </article>
            @endif
        </div>
    </section>
</x-layouts.app>
