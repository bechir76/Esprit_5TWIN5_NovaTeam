@extends('layouts.layout')

@section('title', $infrastructure->name)

@section('content')
<section data-page="infra" class="flex flex-col gap-3">
  <x-page-header :title="$infrastructure->name" :crumb="'Console / Infrastructures / '.$infrastructure->code">
    <x-slot:badges><x-status-badge :value="$infrastructure->status->label()" :variant="$infrastructure->status->badge()" dot />@unless ($infrastructure->is_public)<x-status-badge value="Non publique" variant="b-mute" />@endunless</x-slot:badges>
    <div class="flex flex-wrap items-center gap-2.5"><a class="btn btn-ghost" href="{{ route('admin.infrastructures.edit', $infrastructure) }}">Modifier</a><button class="btn btn-danger" type="button" data-del="{{ $infrastructure->name }}" data-action="{{ route('admin.infrastructures.destroy', $infrastructure) }}">Supprimer</button></div>
  </x-page-header>
  <div class="grid items-start gap-[18px] min-[1101px]:grid-cols-[minmax(0,1fr)_340px]">
    <div class="flex flex-col gap-[18px]">
      <div class="card p-[18px] flex flex-col gap-4">
        <div class="grid grid-fit-[170px] gap-4">
          <div><span class="caption block">Code ouvrage</span><span class="font-mono">{{ $infrastructure->code }}</span></div>
          <div><span class="caption block">Type</span><span>{{ $infrastructure->type->label() }}</span></div>
          <div><span class="caption block">Zone</span><a class="text-info-fg underline" href="{{ route('admin.zones.show', $infrastructure->zone) }}">{{ $infrastructure->zone->name }}</a></div>
          <div><span class="caption block">Commune</span><span>{{ $infrastructure->zone->municipality }} · {{ $infrastructure->zone->governorate }}</span></div>
          <div><span class="caption block">Capacité</span><span class="font-mono">{{ $infrastructure->formattedCapacity() }}</span></div>
          <div><span class="caption block">Matériau</span><span>{{ $infrastructure->material ?? '—' }}</span></div>
          <div><span class="caption block">Mise en service</span><span class="font-mono">{{ $infrastructure->commissioned_year }} · {{ now()->year - $infrastructure->commissioned_year }} ans</span></div>
          <div><span class="caption block">Pression nominale</span><span class="font-mono">{{ $infrastructure->formattedPressure() ?? '—' }}</span></div>
          <div><span class="caption block">Dernière inspection</span><span class="font-mono">{{ $infrastructure->last_inspection_at?->format('d/m/Y') ?? 'Non renseignée' }}</span></div>
          <div><span class="caption block">Carte publique</span><span>{{ $infrastructure->is_public ? 'Visible' : 'Masquée' }}</span></div>
        </div>
        <div><span class="caption block">Description</span><p class="mt-1.5 max-w-[70ch]">{{ $infrastructure->description ?: 'Aucune description.' }}</p></div>
      </div>
      <div class="card">
        <div class="card-header"><h2>Incidents liés</h2><a class="btn btn-primary btn-sm" href="{{ route('admin.incidents.create', ['infrastructure' => $infrastructure->id]) }}">+ Créer un incident</a></div>
        <div class="overflow-x-auto"><table class="data-table"><thead><tr><th>Incident</th><th>Type</th><th>Gravité</th><th>Statut</th><th>Signalé le</th></tr></thead><tbody>
          @forelse ($infrastructure->incidents as $incident)
          <tr>
            <td><span class="mr-2.5 inline-block h-7 w-1 rounded-[2px] align-middle" style="background:{{ $incident->severity->color() }}"></span><span class="inline-block align-middle"><a class="font-semibold" href="{{ route('admin.incidents.show', $incident) }}">{{ $incident->title }}</a><br><span class="font-mono text-muted text-[.76rem]">{{ $incident->reference }}</span></span></td>
            <td>{{ $incident->type->icon() }} {{ $incident->type->label() }}</td>
            <td><x-status-badge :value="$incident->severity->label()" :variant="$incident->severity->badge()" dot /></td>
            <td><x-status-badge :value="$incident->status->label()" :variant="$incident->status->badge()" /></td>
            <td class="font-mono">{{ $incident->created_at->format('d/m/Y') }}</td>
          </tr>
          @empty
          <tr><td colspan="5" class="text-muted text-center p-7">Aucun incident signalé sur cet ouvrage.</td></tr>
          @endforelse
        </tbody></table></div>
      </div>
      {{-- Les modules projets et qualité de l'eau ajouteront ici les éléments liés à l'ouvrage --}}
      <div class="card"><div class="card-header"><h2>Historique</h2></div><div class="p-[18px] timeline">
        @if ($infrastructure->last_inspection_at)
        <div class="timeline-item is-done"><strong>Dernière inspection</strong> : état « {{ $infrastructure->status->label() }} »<div class="text-muted">{{ $infrastructure->last_inspection_at->format('d/m/Y') }}</div></div>
        @endif
        <div class="timeline-item is-done"><strong>Fiche mise à jour</strong><div class="text-muted">{{ $infrastructure->updated_at->format('d/m/Y H:i') }}</div></div>
        <div class="timeline-item pb-0"><strong>Fiche créée</strong><div class="text-muted">{{ $infrastructure->created_at->format('d/m/Y H:i') }}</div></div>
      </div></div>
    </div>
    <aside class="flex flex-col gap-[18px]">
      <div class="card p-[18px] flex flex-col gap-3"><h2>Localisation</h2>
        <x-location-map :latitude="$infrastructure->latitude" :longitude="$infrastructure->longitude" :color="$infrastructure->status->color()" :height="180" />
        @if ($infrastructure->hasCoordinates())
        <span class="font-mono text-muted text-[.8rem]">{{ number_format($infrastructure->latitude, 5, '.', '') }}, {{ number_format($infrastructure->longitude, 5, '.', '') }}</span>
        @endif
      </div>
    </aside>
  </div>
</section>
@endsection
