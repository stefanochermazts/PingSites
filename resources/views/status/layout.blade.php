<!DOCTYPE html>
<html lang="it">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <title>{{ $title }}</title>
    @if (file_exists(public_path('build/manifest.json')) || file_exists(public_path('hot')))
        @vite(['resources/css/status.css'])
    @endif
    @stack('head')
</head>
<body class="status-body">
<a href="#contenuto" class="skip-link">Vai al contenuto</a>
<div class="status-shell">
    @yield('body')
</div>
@include('status.partials.auto-refresh')
@stack('scripts')
</body>
</html>
