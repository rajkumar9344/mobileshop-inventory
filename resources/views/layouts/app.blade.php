<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>@yield('title') || {{ config('app.name') }}</title>
    <meta content="width=device-width, initial-scale=1, maximum-scale=1, user-scalable=no" name="viewport">
    <meta name="csrf-token" content="{{ csrf_token() }}">

    {{-- Apply saved theme before paint to prevent flash of wrong theme --}}
    <script>
        (function () {
            var t = localStorage.getItem('rm-theme') || 'light';
            document.documentElement.setAttribute('data-theme', t);
        })();
    </script>

    <!-- Favicon -->
    <link rel="icon" href="{{ settings()->site_logo ?: asset('images/favicon.png') }}">

    @include('includes.main-css')
</head>

<body class="c-app">
    @include('layouts.sidebar')

    <div class="c-wrapper">
        <header class="c-header c-header-light c-header-fixed">
            @include('layouts.header')
            <div class="c-subheader justify-content-between px-3">
                @yield('breadcrumb')
            </div>
        </header>

        <div class="c-body">
            <main class="c-main">
                @yield('content')
            </main>
        </div>

        @include('layouts.footer')
    </div>

    @include('includes.main-js')

    {{-- Theme toggle logic --}}
    <script>
        (function () {
            function applyTheme(theme) {
                document.documentElement.setAttribute('data-theme', theme);
                localStorage.setItem('rm-theme', theme);
                var icon = document.getElementById('theme-icon');
                if (icon) {
                    icon.className = theme === 'dark' ? 'bi bi-sun-fill' : 'bi bi-moon-fill';
                }
            }

            document.addEventListener('DOMContentLoaded', function () {
                // Sync icon with current saved theme
                var current = localStorage.getItem('rm-theme') || 'light';
                applyTheme(current);

                // Wire toggle button
                var btn = document.getElementById('theme-toggle');
                if (btn) {
                    btn.addEventListener('click', function () {
                        var current = document.documentElement.getAttribute('data-theme') || 'light';
                        applyTheme(current === 'dark' ? 'light' : 'dark');
                    });
                }
            });
        })();
    </script>

    @stack('scripts')
</body>
</html>
