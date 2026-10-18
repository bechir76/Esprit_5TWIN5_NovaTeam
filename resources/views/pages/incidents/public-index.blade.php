@extends('layouts.layout')

@section('title', 'Incidents')

@php
    // Onglets : tous les incidents publics, puis un onglet par statut (les autres filtres sont conservés)
    $tabUrl = fn (?string $status) => route('incidents.index', array_filter(['status' => $status] + \Illuminate\Support\Arr::except($filters, 'status')));
@endphp

@section('content')
<section class="pt-10 pb-16" data-page="incidents">
  <div class="mx-auto max-w-[1200px] px-5">
    <div class="mb-7 flex flex-wrap items-end justify-between gap-4 [&_p]:max-w-[60ch]"><div class="flex flex-col gap-2"><span class="kicker">Suivi public</span><h2>Incidents signalés</h2></div><a class="btn btn-primary" href="{{ route('incidents.create') }}">Signaler un incident</a></div>
    <div class="mb-5 flex gap-1 overflow-x-auto border-b border-line">
      <a href="{{ $tabUrl(null) }}" @class(['-mb-px border-b-2 px-3.5 py-2.5 font-semibold whitespace-nowrap', 'border-water text-ink' => empty($filters['status']), 'border-transparent text-muted hover:text-ink' => ! empty($filters['status'])])>Tous <span class="font-mono text-muted">({{ $counts->sum() }})</span></a>
      @foreach ($statuses as $status)
      <a href="{{ $tabUrl($status->value) }}" @class(['-mb-px border-b-2 px-3.5 py-2.5 font-semibold whitespace-nowrap', 'border-water text-ink' => ($filters['status'] ?? '') === $status->value, 'border-transparent text-muted hover:text-ink' => ($filters['status'] ?? '') !== $status->value])>{{ $status->label() }} <span class="font-mono text-muted">({{ $counts[$status->value] ?? 0 }})</span></a>
      @endforeach
    </div>
    <form class="mb-[18px] flex flex-wrap gap-2.5" method="GET" action="{{ route('incidents.index') }}">
      @if (! empty($filters['status']))
      <input type="hidden" name="status" value="{{ $filters['status'] }}">
      @endif
      <input class="input min-w-[200px] flex-1" type="search" name="q" value="{{ $filters['q'] ?? '' }}" placeholder="Rechercher un incident, une rue…" aria-label="Rechercher">
      <select class="input w-auto" name="type" aria-label="Type" onchange="this.form.submit()">
        <option value="">Tous les types</option>
        @foreach (\App\Enums\IncidentType::cases() as $type)
        <option value="{{ $type->value }}" @selected(($filters['type'] ?? '') === $type->value)>{{ $type->label() }}</option>
        @endforeach
      </select>
      <select class="input w-auto" name="zone" aria-label="Zone" onchange="this.form.submit()">
        <option value="">Toutes les zones</option>
        @foreach ($zones as $id => $name)
        <option value="{{ $id }}" @selected((string) ($filters['zone'] ?? '') === (string) $id)>{{ $name }}</option>
        @endforeach
      </select>
      <button class="btn btn-ghost" type="submit">Rechercher</button>
    </form>
    <div class="overflow-x-auto rounded-card border border-line bg-surface"><table class="data-table"><thead><tr><th>Référence</th><th>Incident</th><th>Type</th><th>Zone</th><th>Gravité</th><th>Statut</th><th>Signalé le</th></tr></thead><tbody>
      @forelse ($incidents as $incident)
      <tr>
        <td class="font-mono text-[.85rem]">{{ $incident->reference }}</td>
        <td><strong>{{ $incident->title }}</strong></td>
        <td>{{ $incident->type->icon() }} {{ $incident->type->label() }}</td>
        <td>{{ $incident->infrastructure?->zone->name ?? '—' }}</td>
        <td><span class="badge {{ $incident->severity->badge() }}">{{ $incident->severity->label() }}</span></td>
        <td><span class="badge {{ $incident->status->badge() }}">{{ $incident->status->label() }}</span></td>
        <td class="font-mono">{{ $incident->created_at->format('d/m/Y') }}</td>
      </tr>
      @empty
      <tr><td colspan="7" class="text-muted text-center p-7">Aucun incident ne correspond à ces critères.</td></tr>
      @endforelse
    </tbody></table></div>
    <div class="mt-4 flex justify-end">{{ $incidents->onEachSide(1)->links('partials.pagination') }}</div>
  </div>
</section>
@endsection
