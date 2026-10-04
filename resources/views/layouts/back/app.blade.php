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

<div class="app" id="app">
  @include('layouts.back.partials.sidebar')

  <div class="main">
    @include('layouts.back.partials.topbar')

    <main class="content">
@yield('content')
    </main>
  </div>
</div>

<x-confirm-delete />
@include('layouts.back.partials.toasts')

{{-- URLs des écrans vers lesquels le JS génère des liens (actions des tableaux de démo) --}}
<script>
window.AQUA_ROUTES = {{ Js::from([
    'incident' => route('admin.incidents.show', 1),
    'infra-form' => route('admin.infrastructures.create'),
    'projet-form' => route('admin.projects.create'),
    'financements' => route('admin.fundings.index'),
]) }};
</script>
<script src="{{ asset('js/back.js') }}"></script>
@stack('scripts')
</body>
</html>
