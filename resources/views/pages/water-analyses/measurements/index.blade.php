@extends('layouts.layout')

@section('title', 'Mesures non conformes')

@php
    $icon = fn (string $paths) => '<svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round">'.$paths.'</svg>';
    $eye   = $icon('<path d="M2 12s3.5-7 10-7 10 7 10 7-3.5 7-10 7S2 12 2 12Z"/><circle cx="12" cy="12" r="3"/>');
    $edit  = $icon('<path d="M12 20h9M16.5 3.5a2.1 2.1 0 0 1 3 3L7 19l-4 1 1-4Z"/>');
    $trash = $icon('<path d="M3 6h18M8 6V4h8v2M19 6l-1 14H6L5 6"/>');
@endphp

@section('content')
<section data-page="measurements" class="flex flex-col gap-3">

  <x-page-header title="Mesures non conformes" crumb="Console / Qualité eau / Non conformes">
    <a class="btn btn-ghost" href="{{ route('admin.water-analyses.index') }}">← Toutes les analyses</a>
  </x-page-header>

  <div class="card">
    <form class="filter-bar" method="GET" action="{{ route('admin.measurements.index') }}">
      <select class="input" name="parameter" aria-label="Paramètre" onchange="this.form.submit()">
        <option value="">Tous les paramètres</option>
        @foreach (\App\Enums\WaterParameter::cases() as $p)
        <option value="{{ $p->value }}" @selected(($filters['parameter'] ?? '') === $p->value)>{{ $p->label() }}</option>
        @endforeach
      </select>
      <select class="input" name="zone" aria-label="Zone" onchange="this.form.submit()">
        <option value="">Toutes les zones</option>
        @foreach ($zones as $id => $name)
        <option value="{{ $id }}" @selected((string) ($filters['zone'] ?? '') === (string) $id)>{{ $name }}</option>
        @endforeach
      </select>
      <button class="btn btn-ghost" type="submit">Filtrer</button>
      @if (array_filter($filters))
      <a class="btn btn-ghost" href="{{ route('admin.measurements.index') }}">Réinitialiser</a>
      @endif
    </form>

    <div class="overflow-x-auto">
      <table class="data-table">
        <thead>
          <tr>
            <th>Paramètre</th>
            <th>Valeur mesurée</th>
            <th>Seuil</th>
            <th>Analyse</th>
            <th>Infrastructure</th>
            <th>Zone</th>
            <th>Date</th>
            <th></th>
          </tr>
        </thead>
        <tbody>
          @forelse ($measurements as $measurement)
          @php
            $param = $measurement->parameter instanceof \App\Enums\WaterParameter
                ? $measurement->parameter
                : \App\Enums\WaterParameter::from($measurement->parameter);
            $analysis = $measurement->waterAnalysis;
            $val = rtrim(rtrim(number_format((float) $measurement->value, 4, ',', ' '), '0'), ',');
          @endphp
          <tr>
            <td><strong>{{ $param->label() }}</strong></td>
            <td class="font-mono text-crit-fg font-semibold">
              {{ $val }}@if ($param->unit()) <small> {{ $param->unit() }}</small>@endif
            </td>
            <td class="text-muted text-[.84rem]">{{ $param->thresholdHint() }}</td>
            <td class="font-mono text-[.82rem]">
              <a class="text-info-fg underline" href="{{ route('admin.water-analyses.show', $analysis) }}">
                {{ $analysis->reference }}
              </a>
            </td>
            <td>
              <a class="underline" href="{{ route('admin.infrastructures.show', $analysis->infrastructure) }}">
                {{ $analysis->infrastructure->name }}
              </a>
            </td>
            <td>{{ $analysis->infrastructure->zone->name ?? '—' }}</td>
            <td class="font-mono whitespace-nowrap">{{ $analysis->sampled_at->format('d/m/Y') }}</td>
            <td>
              <div class="flex justify-end gap-1.5">
                <a class="btn-icon" href="{{ route('admin.water-analyses.show', $analysis) }}" title="Voir l'analyse">{!! $eye !!}</a>
                <a class="btn-icon" href="{{ route('admin.measurements.edit', $measurement) }}" title="Modifier">{!! $edit !!}</a>
                <button class="btn-icon btn-icon-danger" type="button"
                  data-del="{{ $param->label() }}"
                  data-action="{{ route('admin.measurements.destroy', $measurement) }}"
                  title="Supprimer">{!! $trash !!}</button>
              </div>
            </td>
          </tr>
          @empty
          <tr>
            <td colspan="8" class="text-muted text-center p-7">
              Aucune mesure non conforme. 🎉
            </td>
          </tr>
          @endforelse
        </tbody>
      </table>
    </div>

    <div class="card-footer">
      <span class="text-muted">
        @if ($measurements->total())
          Affichage {{ $measurements->firstItem() }} à {{ $measurements->lastItem() }} sur {{ $measurements->total() }}
        @else
          Aucun résultat
        @endif
      </span>
      {{ $measurements->onEachSide(1)->links('partials.pagination') }}
    </div>
  </div>

</section>
@endsection
