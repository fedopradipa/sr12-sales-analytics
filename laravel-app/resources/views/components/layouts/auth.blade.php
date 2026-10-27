@props(['title' => 'Login'])

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta
        name="viewport"
        content="width=device-width, initial-scale=1.0"
    >
    <meta name="theme-color" content="#b97979">

    <title>{{ $title }} · Distributor SR12</title>

    @vite([
        'resources/css/app.css',
        'resources/js/app.js',
    ])
</head>

<body class="sr12-auth-body">
    <main class="sr12-auth-shell">
        {{ $slot }}
    </main>
</body>
</html>
