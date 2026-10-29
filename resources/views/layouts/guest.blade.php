<!DOCTYPE html>
<html lang="th-TH">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">
        <x-theme-head />

        <title>เข้าสู่ระบบ | {{ config('app.name', 'VertexPL') }}</title>

        <!-- Styles -->
        <link rel="stylesheet" href="{{ mix('css/fonts.css') }}">
        <link rel="stylesheet" href="{{ mix('css/phosphor-icons.css') }}">
        <link rel="stylesheet" href="{{ mix('css/sweetalert2.css') }}">
        <link rel="stylesheet" href="{{ mix('css/app.css') }}">

        <!-- Scripts -->
        <script src="{{ mix('js/app.js') }}" defer></script>
    </head>
    <body class="bg-slate-100">
        <x-theme-toggle class="theme-toggle--guest" />
        <div class="font-sans text-slate-900 antialiased">
            {{ $slot }}
        </div>
    </body>
</html>
