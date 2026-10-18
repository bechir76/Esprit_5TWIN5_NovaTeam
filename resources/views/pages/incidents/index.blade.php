@extends('layouts.layout')

@section('title', 'Incidents')

@php
    // Icônes des actions (mêmes tracés que back.js)
    $icon = fn (string $paths) => '<svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round">'.$paths.'</svg>';
    $eye = $icon('<path d="M2 12s3.5-7 10-7 10 7 10 7-3.5 7-10 7S2 12 2 12Z"/><circle cx="12" cy="12" r="3"/>');
    $edit = $icon('<path d="M12 20h9M16.5 3.5a2.1 2.1 0 0 1 3 3L7 19l-4 1 1-4Z"/>');
    $trash = $icon('<path d="M3 6h18M8 6V4h8v2M19 6l-1 14H6L5 6"/>');
@endphp

@section('content')
<section data-page="incidents" class="flex flex-col gap-3">
  <x-page-header title="Incidents">
    <div class="flex flex-wrap items-center gap-2.5"><a class="btn btn-ghost" href="{{ route('admin.interventions.index') }}">Interventions</a><a class="btn btn-primary" href="{{ route('admin.incidents.create') }}">+ Créer un incident</a></div>
  </x-page-header>
  <div class="card">
    <form class="filter-bar" method="GET" action="{{ route('admin.incidents.index') }}">
      <input class="input min-w-[200px] flex-1" type="search" name="q" value="{{ $filters['q'] ?? '' }}" placeholder="Référence, titre, adresse…" aria-label="Rechercher">
      <select class="input" name="type" aria-label="Type" onchange="this.form.submit()">
        <option value="">Tous les types</option>
        @foreach (\App\Enums\IncidentType::cases() as $type)
        <option value="{{ $type->value }}" @selected(($filters['type'] ?? '') === $type->value)>{{ $type->label() }}</option>
        @endforeach
      </select>
      <select class="input" name="severity" aria-label="Gravité" onchange="this.form.submit()">
        <option value="">Toutes gravités</option>
        @foreach (\App\Enums\IncidentSeverity::cases() as $severity)
        <option value="{{ $severity->value }}" @selected(($filters['severity'] ?? '') === $severity->value)>{{ $severity->label() }}</option>
        @endforeach
      </select>
      <select class="input" name="status" aria-label="Statut" onchange="this.form.submit()">
        <option value="">Tous les statuts</option>
        @foreach (\App\Enums\IncidentStatus::cases() as $status)
        <option value="{{ $status->value }}" @selected(($filters['status'] ?? '') === $status->value)>{{ $status->label() }}</option>
        @endforeach
      </select>
      <select class="input" name="zone" aria-label="Zone" onchange="this.form.submit()">
        <option value="">Toutes les zones</option>
        @foreach ($zones as $id => $name)
        <option value="{{ $id }}" @selected((string) ($filters['zone'] ?? '') === (string) $id)>{{ $name }}</option>
        @endforeach
      </select>
      <input class="input" type="date" name="depuis" value="{{ $filters['depuis'] ?? '' }}" aria-label="Depuis" onchange="this.form.submit()">
      <button class="btn btn-ghost" type="submit">Filtrer</button>
      @if (array_filter($filters))
      <a class="btn btn-ghost" href="{{ route('admin.incidents.index') }}">Réinitialiser</a>
      @endif
    </form>
    <div class="overflow-x-auto"><table class="data-table"><thead><tr><th>Incident</th><th>Type</th><th>Infrastructure · Zone</th><th>Gravité</th><th>Statut</th><th>Déclarant</th><th>Date</th><th></th></tr></thead><tbody>
      @forelse ($incidents as $incident)
      <tr>
        <td><span class="mr-2.5 inline-block h-7 w-1 rounded-[2px] align-middle" style="background:{{ $incident->severity->color() }}"></span><span class="inline-block align-middle"><a class="font-semibold" href="{{ route('admin.incidents.show', $incident) }}">{{ $incident->title }}</a><br><span class="font-mono text-muted text-[.76rem]">{{ $incident->reference }}</span></span></td>
        <td>{{ $incident->type->icon() }} {{ $incident->type->label() }}</td>
        <td>
          @if ($incident->infrastructure)
          <a class="underline" href="{{ route('admin.infrastructures.show', $incident->infrastructure) }}">{{ $incident->infrastructure->name }}</a><br><span class="text-muted text-[.78rem]">{{ $incident->infrastructure->zone->name }}</span>
          @else
          <span class="text-muted">—</span><br><span class="text-muted text-[.78rem]">{{ $incident->address }}</span>
          @endif
        </td>
        <td><x-status-badge :value="$incident->severity->label()" :variant="$incident->severity->badge()" dot /></td>
        <td><x-status-badge :value="$incident->status->label()" :variant="$incident->status->badge()" /></td>
        <td class="text-muted">{{ $incident->user?->name ?? '—' }}</td>
        <td class="font-mono whitespace-nowrap">{{ $incident->created_at->format('d/m H:i') }}</td>
        <td><div class="flex justify-end gap-1.5">
          <a class="btn-icon" href="{{ route('admin.incidents.show', $incident) }}" title="Voir">{!! $eye !!}</a>
          <a class="btn-icon" href="{{ route('admin.incidents.edit', $incident) }}" title="Modifier">{!! $edit !!}</a>
          <button class="btn-icon btn-icon-danger" type="button" data-del="{{ $incident->reference }}" data-action="{{ route('admin.incidents.destroy', $incident) }}" title="Supprimer">{!! $trash !!}</button>
        </div></td>
      </tr>
      @empty
      <tr><td colspan="8" class="text-muted text-center p-7">Aucun incident ne correspond à ces critères.</td></tr>
      @endforelse
    </tbody></table></div>
    <div class="card-footer">
      <span class="text-muted">@if ($incidents->total())Affichage {{ $incidents->firstItem() }} à {{ $incidents->lastItem() }} sur {{ $incidents->total() }}@else Aucun résultat @endif</span>
      {{ $incidents->onEachSide(1)->links('partials.pagination') }}
    </div>
  </div>
</section>
@endsection
