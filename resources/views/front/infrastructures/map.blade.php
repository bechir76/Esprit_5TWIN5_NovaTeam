@extends('layouts.front.app')

@section('title', 'Carte du réseau')

@php
    // Aucune case cochée dans un groupe = pas de filtre sur ce groupe (toutes les cases apparaissent cochées)
    $types = $filters['type'] ?? [];
    $statuses = $filters['status'] ?? [];
    $check = 'flex items-center gap-2.5 py-1.5 text-[.92rem]';
    $dot = 'mr-1.5 inline-block size-2.5 rounded-full align-middle';
@endphp

@section('content')
<section class="pt-10 pb-16" data-page="carte">
  <div class="mx-auto max-w-[1200px] px-5">
    <div class="mb-7 flex flex-col gap-2">
      <span class="kicker">Infrastructures</span>
      <h2>Carte du réseau d'eau potable</h2>
      <p class="max-w-[60ch] text-muted">État des réservoirs, stations et conduites, mis à jour après chaque inspection.</p>
    </div>

    <div class="grid gap-5 min-[901px]:grid-cols-[280px_1fr]">
      <form class="card flex flex-col gap-[18px] p-[22px]" method="GET" action="{{ route('map') }}">
        <div class="flex flex-col gap-1.5">
          <label class="text-[.88rem] font-semibold" for="mapSearch">Rechercher</label>
          <input class="input" id="mapSearch" type="search" name="q" value="{{ $filters['q'] ?? '' }}" placeholder="Nom ou code d'ouvrage…">
        </div>
        <div><strong class="text-[.88rem]">Type d'infrastructure</strong>
          @foreach (\App\Enums\InfrastructureType::cases() as $type)
          <label class="{{ $check }}"><input class="size-4 accent-water" type="checkbox" name="type[]" value="{{ $type->value }}" @checked(! $types || in_array($type->value, $types, true))> {{ $type->pluralLabel() }}</label>
          @endforeach
        </div>
        <div><strong class="text-[.88rem]">État</strong>
          @foreach (\App\Enums\InfrastructureStatus::cases() as $status)
          <label class="{{ $check }}"><input class="size-4 accent-water" type="checkbox" name="status[]" value="{{ $status->value }}" @checked(! $statuses || in_array($status->value, $statuses, true))><span class="{{ $dot }}" style="background:{{ $status->color() }}"></span>{{ $status->label() }}</label>
          @endforeach
        </div>
        <div class="flex flex-col gap-1.5">
          <label class="text-[.88rem] font-semibold" for="mapZone">Zone</label>
          <select class="input" id="mapZone" name="zone"><option value="">Toutes les zones</option>@foreach ($zones as $id => $name)<option value="{{ $id }}" @selected((string) ($filters['zone'] ?? '') === (string) $id)>{{ $name }}</option>@endforeach</select>
        </div>
        <button class="btn btn-navy" type="submit">Appliquer les filtres</button>
        @if (array_filter($filters))
        <a class="btn btn-ghost btn-sm justify-center" href="{{ route('map') }}">Réinitialiser</a>
        @endif
        <p class="text-[.85rem] text-muted">{{ $sites->count() }} ouvrage(s) affiché(s).</p>
      </form>

      <div class="relative min-h-[520px] overflow-hidden rounded-card border border-line bg-surface-2">
        <svg class="block size-full" id="mapSvg" viewBox="0 0 900 560" role="img" aria-label="Carte schématique du réseau"></svg>
        <div class="absolute bottom-3.5 left-3.5 flex flex-wrap gap-3.5 rounded-[9px] border border-line bg-surface px-3.5 py-2.5 text-[.8rem]">@foreach (\App\Enums\InfrastructureStatus::cases() as $status)<span><span class="{{ $dot }}" style="background:{{ $status->color() }}"></span>{{ $status->label() }}</span>@endforeach</div>
        <div class="card absolute top-3.5 right-3.5 flex w-[290px] max-w-[calc(100%-28px)] flex-col gap-2.5 p-[22px]" id="mapPop"></div>
      </div>
    </div>
  </div>
  {{-- Lu par front.js : infrastructures publiques filtrées et emprise de la carte (Infrastructure::MAP_BOUNDS) --}}
  <script>
  window.AQUA_SITES = {{ Js::from($sites) }};
  window.AQUA_MAP_BOUNDS = {{ Js::from($bounds) }};
  </script>
</section>
@endsection
