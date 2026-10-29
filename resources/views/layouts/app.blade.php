<!DOCTYPE html>
<html lang="th-TH">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">
        <x-theme-head />

        <title>{{ config('app.name', 'VertexPL') }}</title>

        <link rel="stylesheet" href="{{ mix('css/fonts.css') }}">
        <link rel="stylesheet" href="{{ mix('css/phosphor-icons.css') }}">
        <link rel="stylesheet" href="{{ mix('css/sweetalert2.css') }}">
        <link rel="stylesheet" href="{{ mix('css/app.css') }}">
        <script src="{{ mix('js/app.js') }}" defer></script>
    </head>
    <body class="font-sans antialiased text-slate-800">
        <div x-data="{ sidebarOpen: false }" class="min-h-screen bg-slate-100">
            @if (session('success'))
                <div data-flash-type="success" data-flash-message="{{ session('success') }}" class="hidden"></div>
            @endif
            @if (session('error'))
                <div data-flash-type="error" data-flash-message="{{ session('error') }}" class="hidden"></div>
            @elseif ($errors->any())
                <div data-flash-type="error" data-flash-message="{{ $errors->first() }}" class="hidden"></div>
            @endif

            @include('layouts.navigation')

            <div class="lg:pl-64">
                <main class="app-main min-h-screen pt-16">
                    @isset($header)
                        <header class="border-b border-slate-200 bg-white">
                            <div class="px-4 py-5 sm:px-6 lg:px-8">
                                {{ $header }}
                            </div>
                        </header>
                    @endisset

                    {{ $slot }}
                </main>
            </div>
        </div>
    </body>
</html>
