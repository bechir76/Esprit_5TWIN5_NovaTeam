@extends('layouts.back.app')

@section('title', 'Infrastructures')

@php
    // Icônes des actions (mêmes tracés que back.js)
    $icon = fn (string $paths) => '<svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round">'.$paths.'</svg>';
    $eye = $icon('<path d="M2 12s3.5-7 10-7 10 7 10 7-3.5 7-10 7S2 12 2 12Z"/><circle cx="12" cy="12" r="3"/>');
    $edit = $icon('<path d="M12 20h9M16.5 3.5a2.1 2.1 0 0 1 3 3L7 19l-4 1 1-4Z"/>');
    $trash = $icon('<path d="M3 6h18M8 6V4h8v2M19 6l-1 14H6L5 6"/>');
@endphp

@section('content')
<section data-page="infras" class="stack">
  <x-page-header title="Infrastructures">
    <div class="row"><a class="btn btn-ghost" href="{{ route('admin.zones.index') }}">Zones</a><a class="btn btn-primary" href="{{ route('admin.infrastructures.create') }}">+ Ajouter</a></div>
  </x-page-header>
  <div class="card">
    <form class="filters" method="GET" action="{{ route('admin.infrastructures.index') }}">
      <input class="input grow" type="search" name="q" value="{{ $filters['q'] ?? '' }}" placeholder="Nom, code ouvrage…" aria-label="Rechercher">
      <select class="input" name="type" aria-label="Type" onchange="this.form.submit()">
        <option value="">Tous les types</option>
        @foreach (\App\Enums\InfrastructureType::cases() as $type)
        <option value="{{ $type->value }}" @selected(($filters['type'] ?? '') === $type->value)>{{ $type->label() }}</option>
        @endforeach
      </select>
      <select class="input" name="zone" aria-label="Zone" onchange="this.form.submit()">
        <option value="">Toutes les zones</option>
        @foreach ($zones as $id => $name)
        <option value="{{ $id }}" @selected((string) ($filters['zone'] ?? '') === (string) $id)>{{ $name }}</option>
        @endforeach
      </select>
      <select class="input" name="status" aria-label="État" onchange="this.form.submit()">
        <option value="">Tous les états</option>
        @foreach (\App\Enums\InfrastructureStatus::cases() as $status)
        <option value="{{ $status->value }}" @selected(($filters['status'] ?? '') === $status->value)>{{ $status->label() }}</option>
        @endforeach
      </select>
      <button class="btn btn-ghost" type="submit">Filtrer</button>
      @if (array_filter($filters))
      <a class="btn btn-ghost" href="{{ route('admin.infrastructures.index') }}">Réinitialiser</a>
      @endif
    </form>
    <div class="table-wrap"><table><thead><tr><th>Code</th><th>Nom</th><th>Type</th><th>Zone</th><th>Capacité</th><th>État</th><th>Dernière inspection</th><th></th></tr></thead><tbody>
      @forelse ($infrastructures as $infrastructure)
      <tr>
        <td class="mono" style="font-size:.8rem">{{ $infrastructure->code }}</td>
        <td><a href="{{ route('admin.infrastructures.show', $infrastructure) }}" style="text-decoration:none"><strong>{{ $infrastructure->name }}</strong></a>@unless ($infrastructure->is_public)<br><span class="muted" style="font-size:.76rem">Masquée sur la carte publique</span>@endunless</td>
        <td>{{ $infrastructure->type->label() }}</td>
        <td><a href="{{ route('admin.zones.show', $infrastructure->zone) }}" style="color:inherit">{{ $infrastructure->zone->name }}</a></td>
        <td class="mono">{{ $infrastructure->formattedCapacity() }}</td>
        <td><x-status-badge :value="$infrastructure->status->label()" :variant="$infrastructure->status->badge()" dot /></td>
        <td class="mono">{{ $infrastructure->last_inspection_at?->format('d/m/Y') ?? '—' }}</td>
        <td><div class="acts">
          <a class="act" href="{{ route('admin.infrastructures.show', $infrastructure) }}" title="Voir">{!! $eye !!}</a>
          <a class="act" href="{{ route('admin.infrastructures.edit', $infrastructure) }}" title="Modifier">{!! $edit !!}</a>
          <button class="act del" type="button" data-del="{{ $infrastructure->name }}" data-action="{{ route('admin.infrastructures.destroy', $infrastructure) }}" title="Supprimer">{!! $trash !!}</button>
        </div></td>
      </tr>
      @empty
      <tr><td colspan="8" class="muted" style="text-align:center;padding:28px">Aucune infrastructure ne correspond à ces critères.</td></tr>
      @endforelse
    </tbody></table></div>
    <div class="foot">
      <span class="muted">@if ($infrastructures->total())Affichage {{ $infrastructures->firstItem() }} à {{ $infrastructures->lastItem() }} sur {{ $infrastructures->total() }}@else Aucun résultat @endif</span>
      {{ $infrastructures->onEachSide(1)->links('back.partials.pagination') }}
    </div>
  </div>
</section>
@endsection
