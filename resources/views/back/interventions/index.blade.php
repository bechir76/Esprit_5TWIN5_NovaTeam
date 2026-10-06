@extends('layouts.back.app')

@section('title', 'Interventions')

@php
    $icon = fn (string $paths) => '<svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round">'.$paths.'</svg>';
    $eye = $icon('<path d="M2 12s3.5-7 10-7 10 7 10 7-3.5 7-10 7S2 12 2 12Z"/><circle cx="12" cy="12" r="3"/>');
    $edit = $icon('<path d="M12 20h9M16.5 3.5a2.1 2.1 0 0 1 3 3L7 19l-4 1 1-4Z"/>');
    $trash = $icon('<path d="M3 6h18M8 6V4h8v2M19 6l-1 14H6L5 6"/>');
@endphp

@section('content')
<section data-page="interventions" class="stack">
  <x-page-header title="Interventions planifiées" crumb="Console / Interventions">
    <div class="row"><a class="btn btn-ghost" href="{{ route('admin.incidents.index') }}">Incidents</a><a class="btn btn-primary" href="{{ route('admin.interventions.create') }}">+ Planifier</a></div>
  </x-page-header>
  <div class="card">
    <form class="filters" method="GET" action="{{ route('admin.interventions.index') }}">
      <input class="input grow" type="search" name="q" value="{{ $filters['q'] ?? '' }}" placeholder="Intitulé, équipe, référence incident…" aria-label="Rechercher">
      <select class="input" name="status" aria-label="Statut" onchange="this.form.submit()">
        <option value="">Tous les statuts</option>
        @foreach (\App\Enums\InterventionStatus::cases() as $status)
        <option value="{{ $status->value }}" @selected(($filters['status'] ?? '') === $status->value)>{{ $status->label() }}</option>
        @endforeach
      </select>
      <input class="input" type="date" name="date" value="{{ $filters['date'] ?? '' }}" aria-label="Date prévue" onchange="this.form.submit()">
      <button class="btn btn-ghost" type="submit">Filtrer</button>
      @if (array_filter($filters))
      <a class="btn btn-ghost" href="{{ route('admin.interventions.index') }}">Réinitialiser</a>
      @endif
    </form>
    <div class="table-wrap"><table><thead><tr><th>Date</th><th>Intervention</th><th>Incident lié</th><th>Équipe</th><th>Technicien</th><th>Durée estimée</th><th>Statut</th><th></th></tr></thead><tbody>
      @forelse ($interventions as $intervention)
      <tr>
        <td class="mono" style="white-space:nowrap">{{ $intervention->scheduled_at->format('d/m H:i') }}</td>
        <td><a href="{{ route('admin.interventions.show', $intervention) }}" style="text-decoration:none"><strong>{{ $intervention->title }}</strong></a>@if ($intervention->incident->infrastructure)<br><span class="muted" style="font-size:.78rem">{{ $intervention->incident->infrastructure->name }}</span>@endif</td>
        <td class="mono"><a href="{{ route('admin.incidents.show', $intervention->incident) }}" style="color:inherit">{{ $intervention->incident->reference }}</a></td>
        <td>{{ $intervention->team }}</td>
        <td class="muted">{{ $intervention->technician?->name ?? '—' }}</td>
        <td class="mono">{{ $intervention->formattedDuration() }}</td>
        <td><x-status-badge :value="$intervention->status->label()" :variant="$intervention->status->badge()" dot /></td>
        <td><div class="acts">
          <a class="act" href="{{ route('admin.interventions.show', $intervention) }}" title="Voir">{!! $eye !!}</a>
          <a class="act" href="{{ route('admin.interventions.edit', $intervention) }}" title="Modifier">{!! $edit !!}</a>
          <button class="act del" type="button" data-del="{{ $intervention->title }}" data-action="{{ route('admin.interventions.destroy', $intervention) }}" title="Supprimer">{!! $trash !!}</button>
        </div></td>
      </tr>
      @empty
      <tr><td colspan="8" class="muted" style="text-align:center;padding:28px">Aucune intervention ne correspond à ces critères.</td></tr>
      @endforelse
    </tbody></table></div>
    <div class="foot">
      <span class="muted">@if ($interventions->total())Affichage {{ $interventions->firstItem() }} à {{ $interventions->lastItem() }} sur {{ $interventions->total() }}@else Aucun résultat @endif</span>
      {{ $interventions->onEachSide(1)->links('back.partials.pagination') }}
    </div>
  </div>
</section>
@endsection
