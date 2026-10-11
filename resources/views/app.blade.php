<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Hoxen - Concièrgerie d'élite</title>
        <script>window.__MONEY__ = @json(['usd_rate' => config('money.usd_rate')]);</script>
        @viteReactRefresh
        @vite(['resources/css/app.css', 'resources/js/app.jsx'])
        <link rel="icon" type="image/svg+xml" href="{{ asset('images/logo-white.svg') }}">
</head>
<body class="bg-white text-neutral-900 antialiased">
    <div id="root"></div>
</body>
</html>
