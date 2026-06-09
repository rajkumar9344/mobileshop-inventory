<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>@yield('page-title') - {{ config('app.name') }}</title>
    <meta content="Fahim Anzam Dip" name="author">
    <meta content='width=device-width, initial-scale=1, maximum-scale=1, user-scalable=no' name='viewport'>

    <!-- Favicon -->
    <link rel="icon" href="{{ settings()->site_logo ?: asset('images/favicon.png') }}">

    <!-- CoreUI CSS -->
    @vite('resources/sass/app.scss')
    <!-- Bootstrap Icons -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.5.0/font/bootstrap-icons.css">
    <!-- Error Pages CSS -->
    <link rel="stylesheet" href="{{ asset('css/error-pages.css') }}">
</head>

<body class="c-app">
    <div class="error-page @yield('page-class')">
        <div class="floating-elements">
            <div class="floating-element"></div>
            <div class="floating-element"></div>
            <div class="floating-element"></div>
            <div class="floating-element"></div>
        </div>

        <div class="error-card">
            <div class="error-icon">
                @yield('error-icon')
            </div>

            <div class="error-code">@yield('error-code')</div>
            <h1 class="error-title">@yield('error-title')</h1>
            <p class="error-message">
                @yield('error-message')
            </p>

            <div class="error-actions">
                @yield('error-actions')
            </div>
        </div>
    </div>
</body>
</html>