@php
    // Un seul layout : console de gestion (URL /admin) ou site citoyen.
    // $bare (passé par @extends) : page de la console sans menus, comme la connexion.
    $admin = request()->is('admin', 'admin/*');
    $bare ??= false;
@endphp
<!DOCTYPE html>
<html lang="fr">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
<meta name="csrf-token" content="{{ csrf_token() }}">
<title>@yield('title') · AquaSecure {{ $admin ? 'Gestion' : 'Citoyens' }}</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Manrope:wght@500;700;800&family=IBM+Plex+Sans:wght@400;500;600&family=IBM+Plex+Mono:wght@400;500&display=swap">
@unless ($bare)
{{-- URLs des écrans vers lesquels le JS génère des liens --}}
<script>
window.AQUA_ROUTES = {{ Js::from($admin ? [
    'incident' => route('admin.incidents.show', 1),
    'projet-form' => route('admin.projects.create'),
    'financements' => route('admin.fundings.index'),
] : [
    'projet' => route('projects.show', 1),
    'signaler' => route('incidents.create'),
]) }};
</script>
@endunless
@if ($admin)
@vite(['resources/css/back.css', 'resources/js/back.js'])
@else
@vite(['resources/css/front.css', 'resources/js/front.js'])
@endif
@livewireStyles
@stack('styles')
</head>
<body @class(['antialiased', 'min-h-screen' => ! $admin])>

@if ($admin && $bare)
{{-- Console sans menus (connexion) --}}
@yield('content')
@elseif ($admin)
{{-- Console : sidebar (ouverte par le burger sous 980px), topbar et contenu --}}
<div class="grid min-h-full grid-cols-1 min-[981px]:grid-cols-[252px_1fr]" x-data="{ sidebar: false }" @keydown.escape.window="sidebar = false">
  @include('partials.aside')
  <div class="fixed inset-0 z-30 min-[981px]:hidden" x-show="sidebar" x-cloak @click="sidebar = false"></div>

  <div class="flex min-w-0 flex-col">
    @include('partials.navbar')

    <main class="flex flex-col gap-5 p-4 min-[981px]:p-6">
@yield('content')
    </main>
  </div>
</div>

<x-confirm-delete />
@else
{{-- Site citoyen : en-tête, contenu, pied de page et assistant --}}
@include('partials.navbar')

<main>
@yield('content')
</main>

@include('partials.footer')

<x-chatbot />
@endif

@include('partials.toasts')

@livewireScriptConfig
@stack('scripts')
</body>
</html>
