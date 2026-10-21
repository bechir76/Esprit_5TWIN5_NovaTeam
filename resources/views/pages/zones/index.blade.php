@extends('layouts.layout')

@section('title', 'Zones')

@php
    // Icônes des actions (mêmes tracés que back.js)
    $icon = fn (string $paths) => '<svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round">'.$paths.'</svg>';
    $eye = $icon('<path d="M2 12s3.5-7 10-7 10 7 10 7-3.5 7-10 7S2 12 2 12Z"/><circle cx="12" cy="12" r="3"/>');
    $edit = $icon('<path d="M12 20h9M16.5 3.5a2.1 2.1 0 0 1 3 3L7 19l-4 1 1-4Z"/>');
    $trash = $icon('<path d="M3 6h18M8 6V4h8v2M19 6l-1 14H6L5 6"/>');
@endphp

@section('content')
<section data-page="zones" class="flex flex-col gap-3">
  <x-page-header title="Zones de desserte">
    <a class="btn btn-primary" href="{{ route('admin.zones.create') }}">+ Ajouter une zone</a>
  </x-page-header>
  <div class="card">
    <form class="filter-bar" method="GET" action="{{ route('admin.zones.index') }}">
      <input class="input min-w-[200px] flex-1" type="search" name="q" value="{{ $filters['q'] ?? '' }}" placeholder="Nom, commune ou gouvernorat…" aria-label="Rechercher">
      <button class="btn btn-ghost" type="submit">Rechercher</button>
      @if (array_filter($filters))
      <a class="btn btn-ghost" href="{{ route('admin.zones.index') }}">Réinitialiser</a>
      @endif
    </form>
    <div class="overflow-x-auto"><table class="data-table"><thead><tr><th>Zone</th><th>Commune</th><th>Gouvernorat</th><th>Population desservie</th><th>Infrastructures</th><th>WQRI</th><th></th></tr></thead><tbody>
      @forelse ($zones as $zone)
      @php $wqriLevel = $zone->wqri_level ? \App\Enums\WqriLevel::tryFrom($zone->wqri_level) : null; @endphp
      <tr>
        <td><a href="{{ route('admin.zones.show', $zone) }}"><strong>{{ $zone->name }}</strong></a></td>
        <td>{{ $zone->municipality }}</td>
        <td>{{ $zone->governorate }}</td>
        <td class="font-mono">{{ $zone->population_served ? number_format($zone->population_served, 0, ',', ' ').' hab.' : '—' }}</td>
        <td><a class="font-mono text-info-fg underline" href="{{ route('admin.infrastructures.index', ['zone' => $zone->id]) }}">{{ $zone->infrastructures_count }}</a></td>
        <td>
          @if ($wqriLevel && $zone->wqri_score !== null)
            <div class="flex items-center gap-1.5">
              <span class="font-mono text-[.88rem] font-semibold">{{ $zone->wqri_score }}</span>
              <x-status-badge :value="$wqriLevel->label()" :variant="$wqriLevel->badge()" />
            </div>
          @else
            <span class="text-muted text-[.8rem]">—</span>
          @endif
        </td>
        <td><div class="flex justify-end gap-1.5">
          <a class="btn-icon" href="{{ route('admin.zones.show', $zone) }}" title="Voir">{!! $eye !!}</a>
          <a class="btn-icon" href="{{ route('admin.zones.edit', $zone) }}" title="Modifier">{!! $edit !!}</a>
          <button class="btn-icon btn-icon-danger" type="button" data-del="{{ $zone->name }}" data-action="{{ route('admin.zones.destroy', $zone) }}" title="Supprimer">{!! $trash !!}</button>
        </div></td>
      </tr>
      @empty
      <tr><td colspan="7" class="text-muted text-center p-7">Aucune zone ne correspond à cette recherche.</td></tr>
      @endforelse
    </tbody></table></div>
    <div class="card-footer">
      <span class="text-muted">@if ($zones->total())Affichage {{ $zones->firstItem() }} à {{ $zones->lastItem() }} sur {{ $zones->total() }}@else Aucun résultat @endif</span>
      {{ $zones->onEachSide(1)->links('partials.pagination') }}
    </div>
  </div>
</section>
@endsection
