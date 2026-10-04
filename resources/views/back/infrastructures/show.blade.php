@extends('layouts.back.app')

@section('title', $infrastructure->name)

@section('content')
<section data-page="infra" class="stack">
  <x-page-header :title="$infrastructure->name" :crumb="'Console / Infrastructures / '.$infrastructure->code">
    <x-slot:badges><x-status-badge :value="$infrastructure->status->label()" :variant="$infrastructure->status->badge()" dot />@unless ($infrastructure->is_public)<x-status-badge value="Non publique" variant="b-mute" />@endunless</x-slot:badges>
    <div class="row"><a class="btn btn-ghost" href="{{ route('admin.infrastructures.edit', $infrastructure) }}">Modifier</a><button class="btn btn-danger" type="button" data-del="{{ $infrastructure->name }}" data-action="{{ route('admin.infrastructures.destroy', $infrastructure) }}">Supprimer</button></div>
  </x-page-header>
  <div class="detail">
    <div class="stack" style="gap:18px">
      <div class="card card-b stack" style="gap:16px">
        <div class="kv">
          <div><span class="label">Code ouvrage</span><span class="mono">{{ $infrastructure->code }}</span></div>
          <div><span class="label">Type</span><span>{{ $infrastructure->type->label() }}</span></div>
          <div><span class="label">Zone</span><a href="{{ route('admin.zones.show', $infrastructure->zone) }}" style="color:var(--info-fg)">{{ $infrastructure->zone->name }}</a></div>
          <div><span class="label">Commune</span><span>{{ $infrastructure->zone->municipality }} · {{ $infrastructure->zone->governorate }}</span></div>
          <div><span class="label">Capacité</span><span class="mono">{{ $infrastructure->formattedCapacity() }}</span></div>
          <div><span class="label">Matériau</span><span>{{ $infrastructure->material ?? '—' }}</span></div>
          <div><span class="label">Mise en service</span><span class="mono">{{ $infrastructure->commissioned_year }} · {{ now()->year - $infrastructure->commissioned_year }} ans</span></div>
          <div><span class="label">Pression nominale</span><span class="mono">{{ $infrastructure->formattedPressure() ?? '—' }}</span></div>
          <div><span class="label">Dernière inspection</span><span class="mono">{{ $infrastructure->last_inspection_at?->format('d/m/Y') ?? 'Non renseignée' }}</span></div>
          <div><span class="label">Carte publique</span><span>{{ $infrastructure->is_public ? 'Visible' : 'Masquée' }}</span></div>
        </div>
        <div><span class="label">Description</span><p style="margin-top:6px;max-width:70ch">{{ $infrastructure->description ?: 'Aucune description.' }}</p></div>
      </div>
      {{-- Les modules incidents, projets et qualité de l'eau ajouteront ici les éléments liés à l'ouvrage --}}
      <div class="card"><div class="card-h"><h2>Historique</h2></div><div class="card-b timeline">
        @if ($infrastructure->last_inspection_at)
        <div class="tl done"><strong>Dernière inspection</strong> : état « {{ $infrastructure->status->label() }} »<div class="muted">{{ $infrastructure->last_inspection_at->format('d/m/Y') }}</div></div>
        @endif
        <div class="tl done"><strong>Fiche mise à jour</strong><div class="muted">{{ $infrastructure->updated_at->format('d/m/Y H:i') }}</div></div>
        <div class="tl" style="padding-bottom:0"><strong>Fiche créée</strong><div class="muted">{{ $infrastructure->created_at->format('d/m/Y H:i') }}</div></div>
      </div></div>
    </div>
    <aside class="stack" style="gap:18px">
      <div class="card card-b stack"><h2>Localisation</h2>
        <x-location-map :latitude="$infrastructure->latitude" :longitude="$infrastructure->longitude" :color="$infrastructure->status->color()" :height="180" />
        @if ($infrastructure->hasCoordinates())
        <span class="mono muted" style="font-size:.8rem">{{ number_format($infrastructure->latitude, 5, '.', '') }}, {{ number_format($infrastructure->longitude, 5, '.', '') }}</span>
        @endif
      </div>
    </aside>
  </div>
</section>
@endsection
