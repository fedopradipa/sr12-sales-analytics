<section
    class="overflow-hidden rounded-xl border border-amber-200 bg-white shadow-sm"
    aria-labelledby="calibration-skipped-title"
>
    <div class="border-b border-amber-200 bg-amber-50 px-5 py-4">
        <div
            class="flex flex-wrap items-start justify-between gap-3"
        >
            <div>
                <h2
                    id="calibration-skipped-title"
                    class="font-semibold text-amber-900"
                >
                    Produk Dilewati
                </h2>

                <p
                    class="mt-1 max-w-4xl text-xs leading-5 text-amber-800"
                >
                    Produk berikut tidak memperoleh parameter alpha
                    pada penentuan parameter terakhir karena tidak
                    memenuhi kecukupan data Train/Test. Jika sebelumnya
                    memiliki parameter, parameter lama produk tersebut
                    telah dihapus agar tidak digunakan pada peramalan.
                </p>
            </div>

            <div
                class="rounded-full border border-amber-200 bg-white px-3 py-1 text-xs font-semibold text-amber-800"
            >
                {{ $calibrationResult['skipped'] }} produk
            </div>
        </div>

        <div
            class="mt-3 flex flex-wrap gap-x-5 gap-y-1 text-xs text-amber-800"
        >
            <span>
                Test:
                <strong>
                    {{ $calibrationResult['test_months'] }} bulan
                </strong>
            </span>

            @if (! empty($calibrationResult['generated_at']))
                <span>
                    Hasil terakhir:
                    <strong>
                        {{ \Carbon\CarbonImmutable::parse(
                            $calibrationResult['generated_at']
                        )
                            ->setTimezone('Asia/Jakarta')
                            ->locale('id')
                            ->translatedFormat('d M Y, H.i') }}
                    </strong>
                </span>
            @endif
        </div>
    </div>

    <div class="max-h-[24rem] overflow-auto">
        <table class="min-w-full divide-y divide-gray-200 text-sm">
            <thead class="sticky top-0 z-10 bg-gray-50">
                <tr
                    class="text-left text-xs font-semibold uppercase tracking-wide text-gray-500"
                >
                    <th class="px-4 py-3">Kode</th>
                    <th class="px-4 py-3">Nama Produk</th>
                    <th class="px-4 py-3">Alasan Dilewati</th>
                </tr>
            </thead>

            <tbody class="divide-y divide-gray-100 bg-white">
                @foreach (
                    $calibrationResult['skipped_products']
                    as $product
                )
                    <tr class="align-top">
                        <td
                            class="whitespace-nowrap px-4 py-3 font-semibold text-gray-900"
                        >
                            {{ $product['kode'] }}
                        </td>

                        <td class="px-4 py-3 text-gray-800">
                            {{ $product['nama'] }}
                        </td>

                        <td
                            class="px-4 py-3 leading-5 text-amber-800"
                        >
                            {{ $product['reason'] }}
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>
</section>
