<!DOCTYPE html>
<html lang="fr">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
<title>@yield('title') · AquaSecure Gestion</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Manrope:wght@500;700;800&family=IBM+Plex+Sans:wght@400;500;600&family=IBM+Plex+Mono:wght@400;500&display=swap">
<link rel="stylesheet" href="{{ asset('css/back.css') }}">
@stack('styles')
</head>
<body>

@yield('content')

@include('layouts.back.partials.toasts')
<script src="{{ asset('js/back.js') }}"></script>
@stack('scripts')
</body>
</html>
