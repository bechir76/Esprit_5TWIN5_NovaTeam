<!DOCTYPE html>
<html lang="fr">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
<meta name="csrf-token" content="{{ csrf_token() }}">
<title>@yield('title') · AquaSecure Citoyens</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Manrope:wght@500;700;800&family=IBM+Plex+Sans:wght@400;500;600&family=IBM+Plex+Mono:wght@400;500&display=swap">
{{-- URLs des écrans vers lesquels le JS génère des liens (popup de la carte) --}}
<script>
window.AQUA_ROUTES = {{ Js::from([
    'projet' => route('projects.show', 1),
    'signaler' => route('incidents.create'),
]) }};
</script>
@vite(['resources/css/front.css', 'resources/js/front.js'])
@stack('styles')
</head>
<body class="min-h-screen antialiased">

@include('layouts.front.partials.header')

<main>
@yield('content')
</main>

@include('layouts.front.partials.footer')

<x-chatbot />
@include('layouts.front.partials.toast')

@stack('scripts')
</body>
</html>
