@props(['latitude' => null, 'longitude' => null, 'color' => 'var(--color-water)', 'height' => 200, 'pickable' => false, 'latInput' => 'latitude', 'lngInput' => 'longitude'])

@php
    // Carte schématique de l'emprise Infrastructure::MAP_BOUNDS : le repère est placé en % de la largeur / hauteur.
    // pickable : back.js remplit les champs #latInput / #lngInput au clic et déplace le repère à la saisie.
    ['lat' => [$minLat, $maxLat], 'lng' => [$minLng, $maxLng]] = \App\Models\Infrastructure::MAP_BOUNDS;
    $located = is_numeric($latitude) && is_numeric($longitude);
    $left = $located ? max(0, min(100, ($longitude - $minLng) / ($maxLng - $minLng) * 100)) : 50;
    $top = $located ? max(0, min(100, ($maxLat - $latitude) / ($maxLat - $minLat) * 100)) : 50;
@endphp

<div {{ $attributes->class(['relative overflow-hidden rounded-[9px] border border-line bg-surface-2', 'cursor-crosshair' => $pickable]) }} style="height:{{ $height }}px"
  @if ($pickable) data-pick-map data-bounds="{{ implode(',', [$minLat, $maxLat, $minLng, $maxLng]) }}" data-lat="{{ $latInput }}" data-lng="{{ $lngInput }}" @endif>
  <svg viewBox="0 0 600 200" width="100%" height="100%" preserveAspectRatio="none" aria-hidden="true"><rect width="600" height="200" fill="var(--color-surface-2)"/><path d="M0 80 L600 110 M0 160 L600 140 M200 0 L230 200 M430 0 L400 200" stroke="var(--color-line)" stroke-width="12"/></svg>
  <span class="pointer-events-none absolute" data-pin style="left:{{ round($left, 3) }}%;top:{{ round($top, 3) }}%" @unless ($located) hidden @endunless>
    <span class="absolute -top-[26px] -left-[26px] size-[52px] rounded-full opacity-20" style="background:{{ $color }}"></span>
    <span class="absolute -top-[9px] -left-[9px] size-[18px] rounded-full border-3 border-white" style="background:{{ $color }}"></span>
  </span>
  @unless ($located || $pickable)
  <span class="absolute inset-0 grid place-items-center text-[.86rem] text-muted">Coordonnées non renseignées</span>
  @endunless
</div>
