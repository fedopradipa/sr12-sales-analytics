<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Hasil Peramalan Produk</title>
    <style>
        @page { margin: 28px; }
        body { font-family: "DejaVu Sans", sans-serif; font-size: 10px; color: #1f2937; }
        h1 { font-size: 18px; margin: 0 0 6px; }
        .meta { color: #6b7280; margin-bottom: 18px; }
        table { width: 100%; border-collapse: collapse; }
        th, td { border: 1px solid #d1d5db; padding: 7px; }
        th { background: #f3f4f6; text-align: center; }
        .right { text-align: right; }
        .center { text-align: center; }
    </style>
</head>
<body>
@php
    $produk = $hasilDes->produk;
    $proses = $hasilDes->prosesDes;
    $targetLabel = $proses->target
        ?->locale('id')
        ->translatedFormat('F Y') ?? '-';
@endphp

<h1>Hasil Peramalan Produk</h1>
<div class="meta">
    {{ $produk?->kode ?? '-' }} —
    {{ $produk?->nama ?? 'Produk dihapus' }} |
    Target {{ $targetLabel }}
</div>

<table>
    <thead>
        <tr>
            <th>Kode</th>
            <th>Nama Produk</th>
            <th>Prediksi</th>
            <th>Stok Saat Peramalan</th>
            <th>Stok Minimum</th>
            <th>Stok Perlu Ditambah</th>
            <th>Status</th>
        </tr>
    </thead>
    <tbody>
        <tr>
            <td class="center">{{ $produk?->kode ?? '-' }}</td>
            <td>{{ $produk?->nama ?? 'Produk dihapus' }}</td>
            <td class="right">{{ number_format((float) $hasilDes->prediksi, 2, ',', '.') }}</td>
            <td class="right">{{ $inventory['stok'] === null ? '-' : number_format($inventory['stok'], 0, ',', '.') }}</td>
            <td class="right">{{ $inventory['stok_min'] === null ? '-' : number_format($inventory['stok_min'], 0, ',', '.') }}</td>
            <td class="right">{{ $inventory['estimasi'] === null ? '-' : number_format($inventory['estimasi'], 0, ',', '.') }}</td>
            <td class="center">{{ $inventory['status_label'] }}</td>
        </tr>
    </tbody>
</table>
</body>
</html>
