<!DOCTYPE html>
<html lang="fr">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
<meta name="csrf-token" content="{{ csrf_token() }}">
<title>@yield('title') · AquaSecure Gestion</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Manrope:wght@500;700;800&family=IBM+Plex+Sans:wght@400;500;600&family=IBM+Plex+Mono:wght@400;500&display=swap">
@vite(['resources/css/back.css', 'resources/js/back.js'])
@livewireStyles
@stack('styles')
</head>
<body class="antialiased">

@yield('content')

@include('layouts.back.partials.toasts')

@livewireScriptConfig
@stack('scripts')
</body>
</html>
