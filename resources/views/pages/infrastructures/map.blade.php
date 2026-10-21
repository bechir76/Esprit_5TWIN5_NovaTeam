@extends('layouts.layout')

@section('title', 'Carte du réseau')

{{-- Carte publique Leaflet : composant Livewire InfrastructureMap en mode public (ouvrages publics seulement). --}}
@include('partials.leaflet-assets')

@section('content')
<section class="pt-10 pb-16" data-page="carte">
  <div class="mx-auto flex max-w-[1200px] flex-col gap-3 px-5">
    <div class="mb-4 flex flex-col gap-2">
      <span class="kicker">Infrastructures</span>
      <h2>Carte du réseau d'eau potable</h2>
      <p class="max-w-[60ch] text-muted">État des réservoirs, stations et conduites, mis à jour après chaque inspection. Les repères proches sont regroupés : zoomez pour les détailler.</p>
    </div>

    <livewire:infrastructure-map :public="true" :type="$initial['type']" :status="$initial['status']" :zone="$initial['zone']" />
  </div>
</section>
@endsection
