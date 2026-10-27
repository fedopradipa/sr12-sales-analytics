<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Laporan Peramalan Penjualan</title>
    <style>
        @page { margin: 22px 24px 28px; }
        * { box-sizing: border-box; }
        body {
            margin: 0;
            color: #2a2223;
            font-family: DejaVu Sans, sans-serif;
            font-size: 8.5px;
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
            width: 105px;
            color: #756568;
            font-weight: 700;
        }
        .section-title {
            margin: 12px 0 6px;
            color: #692c2d;
            font-size: 9px;
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
        .warning th {
            background: #fff6df;
            color: #7a4b00;
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
            LAPORAN PERAMALAN PENJUALAN
        </td>
    </tr>
</table>

<table class="meta">
    <tr>
        <td class="label">Hasil Peramalan</td>
        <td>: Peramalan #{{ $prosesDes->id }}</td>
        <td class="label">Periode Peramalan</td>
        <td>
            :
            {{
                $prosesDes->target
                    ->locale('id')
                    ->translatedFormat('F Y')
            }}
        </td>
    </tr>
    <tr>
        <td class="label">Sumber Segmentasi</td>
        <td>: Segmentasi #{{ $prosesDes->kmeans_id }}</td>
        <td class="label">Periode Segmentasi</td>
        <td>
            :
            {{
                \App\Exports\AnalyticsExportFormatter::dateRange(
                    $prosesDes->hasilKmeans->awal,
                    $prosesDes->hasilKmeans->akhir
                )
            }}
        </td>
    </tr>
    <tr>
        <td class="label">Kelompok Produk</td>
        <td>
            :
            Cluster {{ $prosesDes->cluster }}
            —
            {{
                \App\Exports\AnalyticsExportFormatter::clusterLabel(
                    $prosesDes->cluster
                )
            }}
        </td>
        <td class="label">Diproses Oleh</td>
        <td>: {{ $prosesDes->user?->name ?? '-' }}</td>
    </tr>
    <tr>
        <td class="label">Status Proses</td>
        <td>: {{ $coverage['status_label'] }}</td>
        <td class="label">Cakupan Produk</td>
        <td>
            :
            {{ $coverage['forecasted'] }}
            dari
            {{ $coverage['total'] }}
            berhasil diramalkan
        </td>
    </tr>
    <tr>
        <td class="label">Tidak Diramalkan</td>
        <td>: {{ $coverage['skipped'] }} produk</td>
        <td></td>
        <td></td>
    </tr>
</table>

<div class="section-title">HASIL PERAMALAN</div>

<table class="data">
    <thead>
        <tr>
            <th style="width: 28px;">No.</th>
            <th style="width: 65px;">Kode</th>
            <th>Nama Produk</th>
            <th style="width: 78px;">Peramalan Qty</th>
            <th style="width: 68px;">Stok Saat Peramalan</th>
            <th style="width: 74px;">Stok Minimum</th>
            <th style="width: 82px;">Status Persediaan</th>
            <th style="width: 88px;">Stok Perlu Ditambah</th>
        </tr>
    </thead>

    <tbody>
        @forelse ($rows as $row)
            @php
                $hasil = $row['hasil'];
                $inventory = $row['inventory'];
            @endphp

            <tr>
                <td class="center">{{ $loop->iteration }}</td>
                <td class="center nowrap">
                    {{ $hasil->produk?->kode ?? '-' }}
                </td>
                <td>
                    {{ $hasil->produk?->nama ?? 'Produk dihapus' }}
                </td>
                <td class="right">
                    {{ number_format((float) $hasil->prediksi, 2, ',', '.') }}
                </td>
                <td class="right">
                    {{
                        $inventory['stok'] === null
                            ? '-'
                            : number_format(
                                $inventory['stok'],
                                0,
                                ',',
                                '.'
                            )
                    }}
                </td>
                <td class="right">
                    {{
                        $inventory['stok_min'] === null
                            ? '-'
                            : number_format(
                                $inventory['stok_min'],
                                0,
                                ',',
                                '.'
                            )
                    }}
                </td>
                <td class="center">
                    {{ $inventory['status_label'] }}
                </td>
                <td class="right">
                    {{
                        $inventory['estimasi'] === null
                            ? '-'
                            : number_format(
                                $inventory['estimasi'],
                                0,
                                ',',
                                '.'
                            )
                    }}
                </td>
            </tr>
        @empty
            <tr>
                <td colspan="8" class="center">
                    Tidak ada hasil peramalan.
                </td>
            </tr>
        @endforelse
    </tbody>
</table>

@if ($coverage['skipped'] > 0)
    <div class="section-title">
        PRODUK TIDAK DIRAMALKAN
    </div>

    <table class="data warning">
        <thead>
            <tr>
                <th style="width: 28px;">No.</th>
                <th style="width: 70px;">Kode</th>
                <th>Nama Produk</th>
                <th>Keterangan</th>
            </tr>
        </thead>

        <tbody>
            @foreach ($coverage['skipped_products'] as $product)
                <tr>
                    <td class="center">{{ $loop->iteration }}</td>
                    <td class="center">{{ $product['kode'] }}</td>
                    <td>{{ $product['nama'] }}</td>
                    <td>{{ $product['alasan'] }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>
@endif

<div class="footer">
    {{
        \App\Exports\AnalyticsExportFormatter::processTime(
            $prosesDes->created_at
        )
    }}
    · Distributor SR12
</div>
</body>
</html>
