<details open class="overflow-hidden rounded-xl border border-gray-200 bg-white shadow-sm">
    <summary class="cursor-pointer select-none px-5 py-4">
        <div class="inline-flex flex-col gap-1 align-middle">
            <span class="font-semibold text-gray-900">
                Parameter Aktif & Evaluasi
            </span>
            <span class="text-xs font-normal text-gray-500">
                Alpha dipilih dari WAPE Train terendah pada split kronologis. Data Test hanya digunakan untuk mengevaluasi alpha terpilih melalui MAE dan WAPE.
            </span>
        </div>
    </summary>

    <div class="border-t border-gray-200">
        <div class="max-h-[28rem] overflow-auto">
            <table class="min-w-full divide-y divide-gray-200 text-sm">
                <thead class="sticky top-0 z-10 bg-gray-50">
                    <tr class="text-left text-xs font-semibold uppercase tracking-wide text-gray-500">
                        <th class="px-4 py-3">Produk</th>
                        <th class="px-4 py-3 text-center">Alpha</th>
                        <th class="px-4 py-3">Train</th>
                        <th class="px-4 py-3">Test</th>
                        <th class="px-4 py-3 text-center">Bulan Test</th>
                        <th class="px-4 py-3">Periode Data</th>
                        <th class="px-4 py-3">Diperbarui</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100 bg-white">
                    @forelse ($activeParameters as $item)
                        @php
                            $hasAlpha = $item['alpha'] !== null;
                            $hasEvaluation = $hasAlpha
                                && $item['jumlah_test'] !== null
                                && $item['mae_train'] !== null
                                && $item['wape_train'] !== null
                                && $item['mae_test'] !== null
                                && $item['wape_test'] !== null
                                && $item['data_awal'] !== null
                                && $item['data_akhir'] !== null;
                        @endphp
                        <tr class="align-top">
                            <td class="px-4 py-3">
                                <p class="font-semibold text-gray-900">
                                    {{ $item['kode'] }}
                                </p>
                                <p class="mt-0.5 max-w-xs text-xs text-gray-500">
                                    {{ $item['nama'] }}
                                </p>
                            </td>
                            <td class="whitespace-nowrap px-4 py-3 text-center font-semibold text-gray-900">
                                @if ($hasAlpha)
                                    {{ number_format($item['alpha'], 2, ',', '.') }}
                                @else
                                    <span class="text-xs font-medium text-amber-700">
                                        Belum ditentukan
                                    </span>
                                @endif
                            </td>
                            <td class="whitespace-nowrap px-4 py-3">
                                @if ($hasEvaluation)
                                    <div>MAE {{ number_format($item['mae_train'], 4, ',', '.') }}</div>
                                    <div class="text-xs text-gray-500">
                                        WAPE {{ number_format($item['wape_train'], 4, ',', '.') }}%
                                    </div>
                                @elseif ($hasAlpha)
                                    <span class="text-xs text-gray-500">Evaluasi belum tersedia</span>
                                @else
                                    -
                                @endif
                            </td>
                            <td class="whitespace-nowrap px-4 py-3">
                                @if ($hasEvaluation)
                                    <div>MAE {{ number_format($item['mae_test'], 4, ',', '.') }}</div>
                                    <div class="text-xs font-medium text-gray-700">
                                        WAPE {{ number_format($item['wape_test'], 4, ',', '.') }}%
                                    </div>
                                @elseif ($hasAlpha)
                                    <span class="text-xs text-gray-500">Evaluasi belum tersedia</span>
                                @else
                                    -
                                @endif
                            </td>
                            <td class="whitespace-nowrap px-4 py-3 text-center">
                                {{ $hasEvaluation ? $item['jumlah_test'].' bulan' : '-' }}
                            </td>
                            <td class="whitespace-nowrap px-4 py-3 text-xs text-gray-600">
                                @if ($hasEvaluation)
                                    {{ $item['data_awal']->locale('id')->translatedFormat('M Y') }}
                                    –
                                    {{ $item['data_akhir']->locale('id')->translatedFormat('M Y') }}
                                @else
                                    -
                                @endif
                            </td>
                            <td class="whitespace-nowrap px-4 py-3 text-xs text-gray-600">
                                @if ($hasEvaluation && $item['updated_at'])
                                    {{ $item['updated_at']->copy()->setTimezone('Asia/Jakarta')->locale('id')->translatedFormat('d M Y, H.i') }}
                                @else
                                    -
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7" class="px-6 py-8 text-center text-sm text-gray-500">
                                Belum ada produk aktif.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</details>
