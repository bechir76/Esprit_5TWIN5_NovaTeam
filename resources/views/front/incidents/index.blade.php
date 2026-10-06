@extends('layouts.front.app')

@section('title', 'Incidents')

@php
    // Onglets : tous les incidents publics, puis un onglet par statut (les autres filtres sont conservés)
    $tabUrl = fn (?string $status) => route('incidents.index', array_filter(['status' => $status] + \Illuminate\Support\Arr::except($filters, 'status')));
@endphp

@section('content')
<section class="screen" data-page="incidents">
  <div class="wrap">
    <div class="screen-head"><div class="stack" style="gap:8px"><span class="eyebrow">Suivi public</span><h2>Incidents signalés</h2></div><a class="btn btn-primary" href="{{ route('incidents.create') }}">Signaler un incident</a></div>
    <div class="tabs">
      <a href="{{ $tabUrl(null) }}" @class(['on' => empty($filters['status'])])>Tous <span class="mono muted">({{ $counts->sum() }})</span></a>
      @foreach ($statuses as $status)
      <a href="{{ $tabUrl($status->value) }}" @class(['on' => ($filters['status'] ?? '') === $status->value])>{{ $status->label() }} <span class="mono muted">({{ $counts[$status->value] ?? 0 }})</span></a>
      @endforeach
    </div>
    <form class="toolbar" method="GET" action="{{ route('incidents.index') }}">
      @if (! empty($filters['status']))
      <input type="hidden" name="status" value="{{ $filters['status'] }}">
      @endif
      <input class="input" type="search" name="q" value="{{ $filters['q'] ?? '' }}" placeholder="Rechercher un incident, une rue…" aria-label="Rechercher">
      <select class="input" name="type" aria-label="Type" onchange="this.form.submit()">
        <option value="">Tous les types</option>
        @foreach (\App\Enums\IncidentType::cases() as $type)
        <option value="{{ $type->value }}" @selected(($filters['type'] ?? '') === $type->value)>{{ $type->label() }}</option>
        @endforeach
      </select>
      <select class="input" name="zone" aria-label="Zone" onchange="this.form.submit()">
        <option value="">Toutes les zones</option>
        @foreach ($zones as $id => $name)
        <option value="{{ $id }}" @selected((string) ($filters['zone'] ?? '') === (string) $id)>{{ $name }}</option>
        @endforeach
      </select>
      <button class="btn btn-ghost" type="submit">Rechercher</button>
    </form>
    <div class="table-wrap"><table><thead><tr><th>Référence</th><th>Incident</th><th>Type</th><th>Zone</th><th>Gravité</th><th>Statut</th><th>Signalé le</th></tr></thead><tbody>
      @forelse ($incidents as $incident)
      <tr>
        <td class="mono" style="font-size:.85rem">{{ $incident->reference }}</td>
        <td><strong>{{ $incident->title }}</strong></td>
        <td>{{ $incident->type->icon() }} {{ $incident->type->label() }}</td>
        <td>{{ $incident->infrastructure?->zone->name ?? '—' }}</td>
        <td><span class="badge {{ $incident->severity->badge() }}">{{ $incident->severity->label() }}</span></td>
        <td><span class="badge {{ $incident->status->badge() }}">{{ $incident->status->label() }}</span></td>
        <td class="mono">{{ $incident->created_at->format('d/m/Y') }}</td>
      </tr>
      @empty
      <tr><td colspan="7" class="muted" style="text-align:center;padding:28px">Aucun incident ne correspond à ces critères.</td></tr>
      @endforelse
    </tbody></table></div>
    {{ $incidents->onEachSide(1)->links('back.partials.pagination') }}
  </div>
</section>
@endsection
