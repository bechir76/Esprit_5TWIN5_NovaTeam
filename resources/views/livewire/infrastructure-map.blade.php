{{-- Composant Livewire InfrastructureMap : la carte Leaflet (wire:ignore) n'est créée qu'une fois ;
     les filtres renvoient les repères par l'événement « infrastructure-map-updated ».
     Les pages qui l'incluent chargent Leaflet avec @include('partials.leaflet-assets'). --}}
<div class="card">
  <div class="filter-bar">
    <select class="input" wire:model.live="type" aria-label="Type">
      <option value="">Tous les types</option>
      @foreach (\App\Enums\InfrastructureType::cases() as $case)
      <option value="{{ $case->value }}">{{ $case->pluralLabel() }}</option>
      @endforeach
    </select>
    <select class="input" wire:model.live="status" aria-label="État">
      <option value="">Tous les états</option>
      @foreach (\App\Enums\InfrastructureStatus::cases() as $case)
      <option value="{{ $case->value }}">{{ $case->label() }}</option>
      @endforeach
    </select>
    <select class="input" wire:model.live="zone" aria-label="Zone">
      <option value="">Toutes les zones</option>
      @foreach ($zones as $id => $name)
      <option value="{{ $id }}">{{ $name }}</option>
      @endforeach
    </select>
    <select class="input" wire:model.live="risk" aria-label="Niveau de risque">
      <option value="">Tous les risques</option>
      @foreach (\App\Enums\RiskLevel::cases() as $case)
      <option value="{{ $case->value }}">Risque {{ mb_strtolower($case->label()) }}</option>
      @endforeach
    </select>
    @if ($filtered)
    <button class="btn btn-ghost" type="button" wire:click="resetFilters">Réinitialiser</button>
    @endif
    <span class="self-center text-[.85rem] text-muted"><span class="font-mono">{{ count($markers) }}</span> ouvrage(s) affiché(s)</span>
    <span class="self-center text-[.8rem] text-muted" wire:loading.delay>Mise à jour…</span>
  </div>
  <div wire:ignore>
    <div class="h-[520px] w-full" style="z-index:0" data-infrastructure-map role="region" aria-label="Carte des ouvrages du réseau"></div>
  </div>
  <div class="card-footer flex flex-wrap gap-4 text-[.8rem] text-muted">
    @foreach (\App\Enums\InfrastructureStatus::cases() as $case)
    <span class="flex items-center gap-1.5"><span class="inline-block size-3 rounded-full" style="background:{{ $case->hex() }}"></span>{{ $case->label() }}</span>
    @endforeach
  </div>
</div>

@script
<script>
  const el = $wire.$el.querySelector('[data-infrastructure-map]');
  const isPublic = @js($public);

  if (window.L && el) {
    const map = L.map(el).setView([36.82, 10.18], 10);
    L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', { maxZoom: 18, attribution: '&copy; OpenStreetMap' }).addTo(map);
    const cluster = L.markerClusterGroup({ showCoverageOnHover: false });
    map.addLayer(cluster);

    const escape = (s) => String(s ?? '').replace(/[&<>"']/g, (c) => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' })[c]);
    const popup = (m) => `<strong>${escape(m.name)}</strong><br><span style="font-family:monospace">${escape(m.code)}</span>`
      + `<br>${escape(m.type)} · ${escape(m.zone)}`
      + `<br>État : <span style="color:${escape(m.color)};font-weight:600">${escape(m.statusLabel)}</span>`
      + `<br>Risque : ${m.riskScore === null ? 'non évalué' : `<span style="color:${escape(m.riskColor)};font-weight:600">${escape(m.riskLabel)}</span> (${escape(m.riskScore)}/100)`}`
      + `<br>Prochaine inspection : ${escape(m.nextInspection ?? '—')}${m.overdue ? ' <strong style="color:#E63946">(en retard)</strong>' : ''}`
      + `<br><a href="${escape(m.url)}">${isPublic ? 'Signaler un problème' : 'Voir la fiche'}</a>`;

    const draw = (markers) => {
      cluster.clearLayers();
      const layers = markers.map((m) => L.circleMarker([m.lat, m.lng], { radius: 9, color: '#fff', weight: 2, fillColor: m.color, fillOpacity: .95 }).bindPopup(popup(m)));
      cluster.addLayers(layers);
      if (layers.length) map.fitBounds(L.latLngBounds(layers.map((l) => l.getLatLng())).pad(0.2), { maxZoom: 14 });
    };

    draw({{ Js::from($markers) }});
    $wire.on('infrastructure-map-updated', ({ markers }) => draw(markers));
  }
</script>
@endscript
