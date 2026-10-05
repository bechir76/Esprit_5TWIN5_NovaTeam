@extends('layouts.front.app')

@section('title', 'Carte du réseau')

@php
    // Aucune case cochée dans un groupe = pas de filtre sur ce groupe (toutes les cases apparaissent cochées)
    $types = $filters['type'] ?? [];
    $statuses = $filters['status'] ?? [];
@endphp

@section('content')
<section class="screen" data-page="carte">
  <div class="wrap">
    <div class="screen-head"><div class="stack" style="gap:8px"><span class="eyebrow">Infrastructures</span><h2>Carte du réseau d'eau potable</h2><p class="muted">État des réservoirs, stations et conduites, mis à jour après chaque inspection.</p></div></div>
    <div class="map-layout">
      <form class="card pad stack" style="gap:18px" method="GET" action="{{ route('map') }}">
        <div class="field"><label for="mapSearch">Rechercher</label><input class="input" id="mapSearch" type="search" name="q" value="{{ $filters['q'] ?? '' }}" placeholder="Nom ou code d'ouvrage…"></div>
        <div><strong style="font-size:.88rem">Type d'infrastructure</strong>
          @foreach (\App\Enums\InfrastructureType::cases() as $type)
          <label class="check"><input type="checkbox" name="type[]" value="{{ $type->value }}" @checked(! $types || in_array($type->value, $types, true))> {{ $type->pluralLabel() }}</label>
          @endforeach
        </div>
        <div><strong style="font-size:.88rem">État</strong>
          @foreach (\App\Enums\InfrastructureStatus::cases() as $status)
          <label class="check"><input type="checkbox" name="status[]" value="{{ $status->value }}" @checked(! $statuses || in_array($status->value, $statuses, true))><span class="dot" style="background:{{ $status->color() }}"></span>{{ $status->label() }}</label>
          @endforeach
        </div>
        <div class="field"><label for="mapZone">Zone</label><select class="input" id="mapZone" name="zone"><option value="">Toutes les zones</option>@foreach ($zones as $id => $name)<option value="{{ $id }}" @selected((string) ($filters['zone'] ?? '') === (string) $id)>{{ $name }}</option>@endforeach</select></div>
        <button class="btn btn-navy" type="submit">Appliquer les filtres</button>
        @if (array_filter($filters))
        <a class="btn btn-ghost btn-sm" href="{{ route('map') }}" style="justify-content:center">Réinitialiser</a>
        @endif
        <p class="muted" style="font-size:.85rem">{{ $sites->count() }} ouvrage(s) affiché(s).</p>
      </form>
      <div class="map-box">
        <svg id="mapSvg" viewBox="0 0 900 560" role="img" aria-label="Carte schématique du réseau"></svg>
        <div class="map-legend">@foreach (\App\Enums\InfrastructureStatus::cases() as $status)<span><span class="dot" style="background:{{ $status->color() }}"></span>{{ $status->label() }}</span>@endforeach</div>
        <div class="card pad map-pop stack" id="mapPop" style="gap:10px"></div>
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
