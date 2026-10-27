@props([
    'title' => 'Dashboard',
    'role' => null,
])

@php
    $role = auth()->user()?->role ?? $role ?? 'operator';
    $role = strtolower($role);
@endphp

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="theme-color" content="#b97979">

    <title>{{ $title }} · Distributor SR12</title>

    @vite([
        'resources/css/app.css',
        'resources/js/app.js',
    ])
</head>

<body class="sr12-body">
    <div class="sr12-app">
        <x-partials.sidebar :role="$role" />

        <div class="sr12-shell">
            <x-partials.navbar
                :title="$title"
                :role="$role"
            />

            <main class="sr12-content">
                {{ $slot }}
            </main>
        </div>

        <button
            type="button"
            class="sr12-overlay"
            aria-label="Tutup navigasi"
            data-sidebar-close
        ></button>
    </div>
</body>
</html>
