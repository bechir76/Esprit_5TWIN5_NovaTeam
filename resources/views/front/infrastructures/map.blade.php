@extends('layouts.front.app')

@section('title', 'Carte du réseau')

@section('content')
<section class="screen" data-page="carte">
  <div class="wrap">
    <div class="screen-head"><div class="stack" style="gap:8px"><span class="eyebrow">Infrastructures</span><h2>Carte du réseau d'eau potable</h2><p class="muted">État des réservoirs, stations et conduites, mis à jour après chaque inspection.</p></div></div>
    <div class="map-layout">
      <aside class="card pad stack" style="gap:18px">
        <div class="field"><label for="mapSearch">Rechercher</label><input class="input" id="mapSearch" placeholder="Quartier, rue, ouvrage…"></div>
        <div><strong style="font-size:.88rem">Type d'infrastructure</strong>
          <label class="check"><input type="checkbox" checked> Réservoirs</label>
          <label class="check"><input type="checkbox" checked> Stations de pompage</label>
          <label class="check"><input type="checkbox" checked> Stations de traitement</label>
          <label class="check"><input type="checkbox" checked> Conduites principales</label>
        </div>
        <div><strong style="font-size:.88rem">État</strong>
          <label class="check"><input type="checkbox" checked><span class="dot" style="background:var(--teal)"></span>Bon</label>
          <label class="check"><input type="checkbox" checked><span class="dot" style="background:var(--warn)"></span>À surveiller</label>
          <label class="check"><input type="checkbox" checked><span class="dot" style="background:var(--crit)"></span>Critique</label>
        </div>
        <div class="field"><label for="mapZone">Zone</label><select class="input" id="mapZone"><option>Toutes les zones</option><option>Tunis Centre</option><option>Ariana</option><option>Ben Arous</option><option>La Manouba</option></select></div>
        <button class="btn btn-navy" type="button">Appliquer les filtres</button>
      </aside>
      <div class="map-box">
        <svg id="mapSvg" viewBox="0 0 900 560" role="img" aria-label="Carte schématique du réseau"></svg>
        <div class="map-legend"><span><span class="dot" style="background:var(--teal)"></span>Bon</span><span><span class="dot" style="background:var(--warn)"></span>À surveiller</span><span><span class="dot" style="background:var(--crit)"></span>Critique</span></div>
        <div class="card pad map-pop stack" id="mapPop" style="gap:10px"></div>
      </div>
    </div>
  </div>
</section>
@endsection
