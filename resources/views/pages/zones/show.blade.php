@extends('layouts.layout')

@section('title', $zone->name)

@php
    $icon = fn (string $paths) => '<svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round">'.$paths.'</svg>';
    $eye = $icon('<path d="M2 12s3.5-7 10-7 10 7 10 7-3.5 7-10 7S2 12 2 12Z"/><circle cx="12" cy="12" r="3"/>');
    $edit = $icon('<path d="M12 20h9M16.5 3.5a2.1 2.1 0 0 1 3 3L7 19l-4 1 1-4Z"/>');
@endphp

@section('content')
<section data-page="zone" class="flex flex-col gap-3">
  <x-page-header :title="$zone->name" :crumb="'Console / Zones / '.$zone->name">
    <div class="flex flex-wrap items-center gap-2.5"><a class="btn btn-ghost" href="{{ route('admin.zones.edit', $zone) }}">Modifier</a><button class="btn btn-danger" type="button" data-del="{{ $zone->name }}" data-action="{{ route('admin.zones.destroy', $zone) }}">Supprimer</button></div>
  </x-page-header>
  <div class="grid gap-[18px] grid-fit-[170px]">
    <x-kpi-card label="Infrastructures" :value="$infrastructures->count()" />
    @foreach (\App\Enums\InfrastructureStatus::cases() as $status)
    <x-kpi-card :label="$status->label()" :value="$statusCounts[$status->value] ?? 0" />
    @endforeach
  </div>
  <div class="card p-[18px] flex flex-col gap-4">
    <div class="grid grid-fit-[170px] gap-4">
      <div><span class="caption block">Commune</span><span>{{ $zone->municipality }}</span></div>
      <div><span class="caption block">Gouvernorat</span><span>{{ $zone->governorate }} <span class="font-mono text-muted">({{ $zone->governorateCode() }})</span></span></div>
      <div><span class="caption block">Population desservie</span><span class="font-mono">{{ $zone->population_served ? number_format($zone->population_served, 0, ',', ' ').' hab.' : '—' }}</span></div>
      <div><span class="caption block">Créée le</span><span class="font-mono">{{ $zone->created_at->format('d/m/Y') }}</span></div>
    </div>
    @if ($zone->description)
    <div><span class="caption block">Description</span><p class="mt-1.5 max-w-[70ch]">{{ $zone->description }}</p></div>
    @endif
  </div>
  <div class="card">
    <div class="card-header"><h2>Infrastructures de la zone</h2><a class="btn btn-primary btn-sm" href="{{ route('admin.infrastructures.create', ['zone' => $zone->id]) }}">+ Ajouter</a></div>
    <div class="overflow-x-auto"><table class="data-table"><thead><tr><th>Code</th><th>Nom</th><th>Type</th><th>Capacité</th><th>État</th><th>Dernière inspection</th><th></th></tr></thead><tbody>
      @forelse ($infrastructures as $infrastructure)
      <tr>
        <td class="font-mono text-[.8rem]">{{ $infrastructure->code }}</td>
        <td><a href="{{ route('admin.infrastructures.show', $infrastructure) }}"><strong>{{ $infrastructure->name }}</strong></a></td>
        <td>{{ $infrastructure->type->label() }}</td>
        <td class="font-mono">{{ $infrastructure->formattedCapacity() }}</td>
        <td><x-status-badge :value="$infrastructure->status->label()" :variant="$infrastructure->status->badge()" dot /></td>
        <td class="font-mono">{{ $infrastructure->last_inspection_at?->format('d/m/Y') ?? '—' }}</td>
        <td><div class="flex justify-end gap-1.5">
          <a class="btn-icon" href="{{ route('admin.infrastructures.show', $infrastructure) }}" title="Voir">{!! $eye !!}</a>
          <a class="btn-icon" href="{{ route('admin.infrastructures.edit', $infrastructure) }}" title="Modifier">{!! $edit !!}</a>
        </div></td>
      </tr>
      @empty
      <tr><td colspan="7" class="text-muted text-center p-7">Aucune infrastructure dans cette zone : elle peut être supprimée.</td></tr>
      @endforelse
    </tbody></table></div>
  </div>
</section>
@endsection
