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
{{-- URLs des écrans vers lesquels le JS génère des liens --}}
<script>
window.AQUA_ROUTES = {{ Js::from([
    'incident' => route('admin.incidents.show', 1),
    'projet-form' => route('admin.projects.create'),
    'financements' => route('admin.fundings.index'),
]) }};
</script>
@vite(['resources/css/back.css', 'resources/js/back.js'])
@stack('styles')
</head>
<body class="antialiased">

{{-- sidebar : menu latéral ouvert par le burger sous 980px --}}
<div class="grid min-h-full grid-cols-1 min-[981px]:grid-cols-[252px_1fr]" x-data="{ sidebar: false }" @keydown.escape.window="sidebar = false">
  @include('layouts.back.partials.sidebar')
  <div class="fixed inset-0 z-30 min-[981px]:hidden" x-show="sidebar" x-cloak @click="sidebar = false"></div>

  <div class="flex min-w-0 flex-col">
    @include('layouts.back.partials.topbar')

    <main class="flex flex-col gap-5 p-4 min-[981px]:p-6">
@yield('content')
    </main>
  </div>
</div>

<x-confirm-delete />
@include('layouts.back.partials.toasts')

@stack('scripts')
</body>
</html>
