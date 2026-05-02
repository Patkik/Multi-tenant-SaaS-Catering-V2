<!doctype html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <title>CaterPro</title>

        <link rel="preconnect" href="https://fonts.bunny.net">
        <link href="https://fonts.bunny.net/css?family=sora:400,500,600,700|plus-jakarta-sans:400,500,600,700" rel="stylesheet" />

        @php
            $host = request()->getHost();
            $isTenantLocalSubdomain = str_ends_with($host, '.localhost') && $host !== 'localhost';

            if ($isTenantLocalSubdomain) {
                \Illuminate\Support\Facades\Vite::useHotFile(storage_path('framework/vite.hot.disabled'));
            }
        @endphp

        @viteReactRefresh
        @vite(['resources/css/app.css', 'resources/js/app.js'])
    </head>
    <body>
        <div id="app"></div>
    </body>
</html>
