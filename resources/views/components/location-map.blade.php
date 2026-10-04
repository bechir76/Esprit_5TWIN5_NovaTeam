@props(['latitude' => null, 'longitude' => null, 'color' => 'var(--water)', 'height' => 200, 'pickable' => false, 'latInput' => 'latitude', 'lngInput' => 'longitude'])

@php
    // Carte schématique de l'emprise Infrastructure::MAP_BOUNDS : le repère est placé en % de la largeur / hauteur.
    // pickable : back.js remplit les champs #latInput / #lngInput au clic et déplace le repère à la saisie.
    ['lat' => [$minLat, $maxLat], 'lng' => [$minLng, $maxLng]] = \App\Models\Infrastructure::MAP_BOUNDS;
    $located = is_numeric($latitude) && is_numeric($longitude);
    $left = $located ? max(0, min(100, ($longitude - $minLng) / ($maxLng - $minLng) * 100)) : 50;
    $top = $located ? max(0, min(100, ($maxLat - $latitude) / ($maxLat - $minLat) * 100)) : 50;
@endphp

<div {{ $attributes->class(['mapbox']) }} style="height:{{ $height }}px;position:relative;{{ $pickable ? 'cursor:crosshair' : '' }}"
  @if ($pickable) data-pick-map data-bounds="{{ implode(',', [$minLat, $maxLat, $minLng, $maxLng]) }}" data-lat="{{ $latInput }}" data-lng="{{ $lngInput }}" @endif>
  <svg viewBox="0 0 600 200" width="100%" height="100%" preserveAspectRatio="none" aria-hidden="true"><rect width="600" height="200" fill="var(--surface-2)"/><path d="M0 80 L600 110 M0 160 L600 140 M200 0 L230 200 M430 0 L400 200" stroke="var(--line)" stroke-width="12"/></svg>
  <span data-pin style="position:absolute;left:{{ round($left, 3) }}%;top:{{ round($top, 3) }}%;pointer-events:none" @unless ($located) hidden @endunless>
    <span style="position:absolute;left:-26px;top:-26px;width:52px;height:52px;border-radius:50%;background:{{ $color }};opacity:.2"></span>
    <span style="position:absolute;left:-9px;top:-9px;width:18px;height:18px;border-radius:50%;background:{{ $color }};border:3px solid #fff;box-sizing:border-box"></span>
  </span>
  @unless ($located || $pickable)
  <span class="muted" style="position:absolute;inset:0;display:grid;place-items:center;font-size:.86rem">Coordonnées non renseignées</span>
  @endunless
</div>
