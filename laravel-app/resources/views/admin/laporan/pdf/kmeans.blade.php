<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Laporan Segmentasi Produk</title>
    <style>
        @page { margin: 22px 24px 28px; }
        * { box-sizing: border-box; }
        body {
            margin: 0;
            color: #2a2223;
            font-family: DejaVu Sans, sans-serif;
            font-size: 9px;
            line-height: 1.35;
        }
        .header {
            width: 100%;
            margin-bottom: 14px;
            padding-bottom: 10px;
            border-bottom: 2px solid #982720;
        }
        .header td { vertical-align: middle; }
        .logo-cell { width: 78px; }
        .logo { width: 64px; height: auto; }
        .brand {
            color: #692c2d;
            font-size: 12px;
            font-weight: 700;
        }
        .brand-subtitle {
            color: #786769;
            font-size: 8.5px;
        }
        .report-title {
            text-align: right;
            color: #982720;
            font-size: 14px;
            font-weight: 700;
        }
        .meta {
            width: 100%;
            margin-bottom: 12px;
            border-collapse: collapse;
        }
        .meta td { padding: 2px 6px 2px 0; }
        .label {
            width: 92px;
            color: #756568;
            font-weight: 700;
        }
        table.data {
            width: 100%;
            border-collapse: collapse;
        }
        .data th,
        .data td {
            padding: 5px;
            border: 1px solid #e2d3d5;
        }
        .data th {
            background: #f4e8e9;
            color: #692c2d;
            font-size: 8px;
            text-align: center;
        }
        .center { text-align: center; }
        .right { text-align: right; }
        .nowrap { white-space: nowrap; }
        .footer {
            margin-top: 12px;
            text-align: right;
            color: #8a7c7e;
            font-size: 7.5px;
        }
    </style>
</head>
<body>
<table class="header">
    <tr>
        <td class="logo-cell">
            @if ($logoData)
                <img src="{{ $logoData }}" alt="Logo SR12" class="logo">
            @endif
        </td>
        <td>
            <div class="brand">Distributor SR12</div>
            <div class="brand-subtitle">
                Sistem Analitik Penjualan
            </div>
        </td>
        <td class="report-title">
            LAPORAN SEGMENTASI PRODUK
        </td>
    </tr>
</table>

<table class="meta">
    <tr>
        <td class="label">Hasil Analisis</td>
        <td>: Segmentasi #{{ $hasilKmeans->id }}</td>
        <td class="label">Jumlah Produk</td>
        <td>: {{ number_format($details->count(), 0, ',', '.') }}</td>
    </tr>
    <tr>
        <td class="label">Periode Analisis</td>
        <td>
            :
            {{
                \App\Exports\AnalyticsExportFormatter::dateRange(
                    $hasilKmeans->awal,
                    $hasilKmeans->akhir
                )
            }}
        </td>
        <td class="label">Jumlah Iterasi</td>
        <td>: {{ number_format($hasilKmeans->iterasi, 0, ',', '.') }}</td>
    </tr>
    <tr>
        <td class="label">Diproses Oleh</td>
        <td>: {{ $hasilKmeans->user?->name ?? '-' }}</td>
        <td class="label">Waktu Proses</td>
        <td>
            :
            {{
                \App\Exports\AnalyticsExportFormatter::processTime(
                    $hasilKmeans->created_at
                )
            }}
        </td>
    </tr>
</table>

<table class="data">
    <thead>
        <tr>
            <th style="width: 28px;">No.</th>
            <th style="width: 68px;">Kode</th>
            <th>Nama Produk</th>
            <th style="width: 58px;">Recency</th>
            <th style="width: 70px;">Hari Terjual</th>
            <th style="width: 68px;">Total Qty</th>
            <th style="width: 105px;">Total Omzet</th>
            <th style="width: 44px;">Cluster</th>
            <th style="width: 108px;">Segmentasi</th>
        </tr>
    </thead>
    <tbody>
        @forelse ($details as $detail)
            <tr>
                <td class="center">{{ $loop->iteration }}</td>
                <td class="center nowrap">
                    {{ $detail->produk?->kode ?? '-' }}
                </td>
                <td>
                    {{ $detail->produk?->nama ?? 'Produk dihapus' }}
                </td>
                <td class="right">
                    {{ number_format($detail->recency, 0, ',', '.') }}
                </td>
                <td class="right">
                    {{ number_format($detail->frekuensi, 0, ',', '.') }}
                </td>
                <td class="right">
                    {{ number_format($detail->qty, 0, ',', '.') }}
                </td>
                <td class="right nowrap">
                    Rp {{ number_format($detail->omzet, 0, ',', '.') }}
                </td>
                <td class="center">{{ $detail->cluster }}</td>
                <td>
                    {{
                        \App\Exports\AnalyticsExportFormatter::clusterLabel(
                            $detail->cluster
                        )
                    }}
                </td>
            </tr>
        @empty
            <tr>
                <td colspan="9" class="center">
                    Tidak ada detail hasil segmentasi.
                </td>
            </tr>
        @endforelse
    </tbody>
</table>

<div class="footer">
    Dicetak
    {{ now()->timezone('Asia/Jakarta')->locale('id')->translatedFormat('d M Y, H.i') }}
    WIB · Distributor SR12
</div>
</body>
</html>
