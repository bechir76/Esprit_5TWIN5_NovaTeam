@extends('layouts.layout')

{{-- Carte des incidents ouverts (site citoyen /incidents/carte et console /admin/incidents/carte).
     Leaflet + regroupement des repères (markercluster) ; filtres type / gravité / statut côté navigateur.
     Les repères ne contiennent aucune donnée personnelle (IncidentReportController::markers). --}}
@php($admin = request()->is('admin*'))

@section('title', 'Carte des incidents')

@push('styles')
<link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" crossorigin="">
<link rel="stylesheet" href="https://unpkg.com/leaflet.markercluster@1.5.3/dist/MarkerCluster.css" crossorigin="">
<link rel="stylesheet" href="https://unpkg.com/leaflet.markercluster@1.5.3/dist/MarkerCluster.Default.css" crossorigin="">
@endpush

@section('content')
<section data-page="incidents-map" @class(['flex flex-col gap-3', 'pt-10 pb-16' => ! $admin])>
  <div @class(['flex flex-col gap-3', 'mx-auto w-full max-w-[1200px] px-5' => ! $admin])>
    @if ($admin)
    <x-page-header title="Carte des incidents" crumb="Console / Incidents / Carte">
      <div class="flex flex-wrap items-center gap-2.5"><a class="btn btn-ghost" href="{{ route('admin.incidents.index') }}">Liste</a></div>
    </x-page-header>
    @else
    <div class="mb-3 flex flex-wrap items-end justify-between gap-4"><div class="flex flex-col gap-2"><span class="kicker">Incidents ouverts</span><h2>Carte des incidents</h2><p class="text-muted max-w-[60ch]">Signalements en cours de traitement. Les repères proches sont regroupés : zoomez pour les détailler.</p></div>
      <div class="flex gap-2.5"><a class="btn btn-ghost" href="{{ route('incidents.index') }}">Liste</a>@auth<a class="btn btn-primary" href="{{ route('incidents.create') }}">Signaler un incident</a>@endauth</div></div>
    @endif

    <div class="card">
      <div class="filter-bar" id="mapFilters">
        <select class="input" data-filter="type" aria-label="Type">
          <option value="">Tous les types</option>
          @foreach (\App\Enums\IncidentType::cases() as $case)
          <option value="{{ $case->value }}">{{ $case->label() }}</option>
          @endforeach
        </select>
        <select class="input" data-filter="severity" aria-label="Gravité">
          <option value="">Toutes gravités</option>
          @foreach (\App\Enums\IncidentSeverity::cases() as $case)
          <option value="{{ $case->value }}">{{ $case->label() }}</option>
          @endforeach
        </select>
        <select class="input" data-filter="status" aria-label="Statut">
          <option value="">Tous les statuts ouverts</option>
          @foreach (\App\Enums\IncidentStatus::openCases() as $case)
          <option value="{{ $case->value }}">{{ $case->label() }}</option>
          @endforeach
        </select>
        <span class="self-center text-[.85rem] text-muted"><span class="font-mono" id="mapCount">{{ count($markers) }}</span> incident(s) affiché(s)</span>
      </div>
      <div id="incidentMap" class="h-[560px] w-full" style="z-index:0" aria-label="Carte des incidents ouverts"></div>
      <div class="card-footer flex flex-wrap gap-4 text-[.8rem] text-muted">
        @foreach (\App\Enums\IncidentSeverity::cases() as $case)
        <span class="flex items-center gap-1.5"><span class="inline-block size-3 rounded-full" style="background:{{ $case->color() }}"></span>{{ $case->label() }}</span>
        @endforeach
      </div>
    </div>
  </div>
</section>
@endsection

@push('scripts')
<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js" crossorigin=""></script>
<script src="https://unpkg.com/leaflet.markercluster@1.5.3/dist/leaflet.markercluster.js" crossorigin=""></script>
<script>
  (() => {
    const markers = @json($markers);
    const map = L.map('incidentMap').setView([36.8, 10.18], 9);
    L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
      maxZoom: 18, attribution: '&copy; OpenStreetMap',
    }).addTo(map);

    const cluster = L.markerClusterGroup({ showCoverageOnHover: false });
    map.addLayer(cluster);

    const escape = (s) => String(s).replace(/[&<>"']/g, (c) => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' })[c]);
    const layers = markers.map((m) => ({
      data: m,
      layer: L.circleMarker([m.lat, m.lng], { radius: 9, color: '#fff', weight: 2, fillColor: m.color, fillOpacity: .95 })
        .bindPopup(`<strong>${escape(m.title)}</strong><br><span style="font-family:monospace">${escape(m.reference)}</span><br>${escape(m.typeLabel)} · ${escape(m.severityLabel)}<br>${escape(m.statusLabel)} · ${escape(m.date)}<br><a href="${escape(m.url)}">Voir</a>`),
    }));

    const filters = document.querySelectorAll('#mapFilters [data-filter]');
    const render = () => {
      const active = Object.fromEntries([...filters].map((f) => [f.dataset.filter, f.value]));
      const visible = layers.filter(({ data }) => Object.entries(active).every(([k, v]) => !v || data[k] === v));
      cluster.clearLayers();
      cluster.addLayers(visible.map((v) => v.layer));
      document.getElementById('mapCount').textContent = visible.length;
      if (visible.length) map.fitBounds(L.latLngBounds(visible.map((v) => v.layer.getLatLng())).pad(0.2), { maxZoom: 14 });
    };
    filters.forEach((f) => f.addEventListener('change', render));
    render();
  })();
</script>
@endpush
