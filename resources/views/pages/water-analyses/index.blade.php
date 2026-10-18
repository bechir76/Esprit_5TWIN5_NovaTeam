@extends('layouts.layout')

@section('title', 'Qualité de l\'eau')

@php
    $icon = fn (string $paths) => '<svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round">'.$paths.'</svg>';
    $eye   = $icon('<path d="M2 12s3.5-7 10-7 10 7 10 7-3.5 7-10 7S2 12 2 12Z"/><circle cx="12" cy="12" r="3"/>');
    $edit  = $icon('<path d="M12 20h9M16.5 3.5a2.1 2.1 0 0 1 3 3L7 19l-4 1 1-4Z"/>');
    $trash = $icon('<path d="M3 6h18M8 6V4h8v2M19 6l-1 14H6L5 6"/>');
@endphp

@section('content')
<section data-page="water-analyses" class="flex flex-col gap-3">

  <x-page-header title="Qualité de l'eau">
    <div class="flex flex-wrap items-center gap-2.5">
      <a class="btn btn-ghost" href="{{ route('admin.measurements.index') }}">Mesures non conformes</a>
      <a class="btn btn-primary" href="{{ route('admin.water-analyses.create') }}">+ Nouvelle analyse</a>
    </div>
  </x-page-header>

  {{-- KPIs réels --}}
  <div class="grid gap-[18px] grid-fit-[170px]">
    <x-kpi-card
      label="Analyses ce mois"
      :value="$kpis['this_month']"
      color="var(--color-water)" />
    <x-kpi-card
      label="Taux de conformité global"
      :value="$kpis['compliance_rate'] !== null ? $kpis['compliance_rate'].' %' : '—'"
      :color="$kpis['compliance_rate'] === 100 ? 'var(--color-teal)' : ($kpis['compliance_rate'] !== null && $kpis['compliance_rate'] < 90 ? 'var(--color-crit)' : 'var(--color-teal)')" />
    <x-kpi-card
      label="Analyses non conformes"
      :value="$kpis['non_compliant']"
      :color="$kpis['non_compliant'] > 0 ? 'var(--color-crit)' : 'var(--color-teal)'" />
    <x-kpi-card
      label="Dernière analyse"
      :value="$kpis['last_analysis'] ? $kpis['last_analysis']->sampled_at->format('d/m/Y') : '—'"
      :delta="$kpis['last_analysis'] ? $kpis['last_analysis']->infrastructure->name : null" />
  </div>

  <div class="card">
    <form class="filter-bar" method="GET" action="{{ route('admin.water-analyses.index') }}">
      <input class="input min-w-[200px] flex-1" type="search" name="q" value="{{ $filters['q'] ?? '' }}"
        placeholder="Référence, point de prélèvement, laboratoire…" aria-label="Rechercher">

      <select class="input" name="zone" aria-label="Zone" onchange="this.form.submit()">
        <option value="">Toutes les zones</option>
        @foreach ($zones as $id => $name)
        <option value="{{ $id }}" @selected((string) ($filters['zone'] ?? '') === (string) $id)>{{ $name }}</option>
        @endforeach
      </select>

      <select class="input" name="infrastructure" aria-label="Infrastructure" onchange="this.form.submit()">
        <option value="">Toutes les infrastructures</option>
        @foreach ($infrastructures as $id => $name)
        <option value="{{ $id }}" @selected((string) ($filters['infrastructure'] ?? '') === (string) $id)>{{ $name }}</option>
        @endforeach
      </select>

      <select class="input" name="status" aria-label="Statut" onchange="this.form.submit()">
        <option value="">Tous les statuts</option>
        @foreach (\App\Enums\AnalysisStatus::cases() as $status)
        <option value="{{ $status->value }}" @selected(($filters['status'] ?? '') === $status->value)>{{ $status->label() }}</option>
        @endforeach
      </select>

      <select class="input" name="periode" aria-label="Période" onchange="this.form.submit()">
        <option value="">Toutes périodes</option>
        <option value="7j"   @selected(($filters['periode'] ?? '') === '7j')>7 derniers jours</option>
        <option value="30j"  @selected(($filters['periode'] ?? '') === '30j')>30 derniers jours</option>
        <option value="90j"  @selected(($filters['periode'] ?? '') === '90j')>3 derniers mois</option>
        <option value="180j" @selected(($filters['periode'] ?? '') === '180j')>6 derniers mois</option>
      </select>

      <button class="btn btn-ghost" type="submit">Filtrer</button>
      @if (array_filter($filters))
      <a class="btn btn-ghost" href="{{ route('admin.water-analyses.index') }}">Réinitialiser</a>
      @endif
    </form>

    <div class="overflow-x-auto">
      <table class="data-table">
        <thead>
          <tr>
            <th>Référence</th>
            <th>Infrastructure · Zone</th>
            <th>Point de prélèvement</th>
            <th>Date</th>
            <th>Technicien</th>
            <th>Mesures</th>
            <th>Conformité</th>
            <th>Statut</th>
            <th></th>
          </tr>
        </thead>
        <tbody>
          @forelse ($analyses as $analysis)
          @php
            $rate = null;
            if ($analysis->measurements_count > 0) {
                $c = $analysis->measurements_count - $analysis->non_compliant_count;
                $rate = (int) round($c / $analysis->measurements_count * 100);
            }
          @endphp
          <tr>
            <td class="font-mono text-[.82rem]">
              <a href="{{ route('admin.water-analyses.show', $analysis) }}">
                <strong>{{ $analysis->reference }}</strong>
              </a>
            </td>
            <td>
              <a class="underline" href="{{ route('admin.infrastructures.show', $analysis->infrastructure) }}">
                <strong>{{ $analysis->infrastructure->name }}</strong>
              </a>
              <br><span class="text-muted text-[.78rem]">{{ $analysis->infrastructure->zone->name ?? '—' }}</span>
            </td>
            <td class="max-w-[180px] whitespace-nowrap overflow-hidden text-ellipsis">
              {{ $analysis->sampling_point }}
            </td>
            <td class="font-mono whitespace-nowrap">{{ $analysis->sampled_at->format('d/m/Y H:i') }}</td>
            <td @class(['text-muted' => ! $analysis->sampledBy])>{{ $analysis->sampledBy?->name ?? '—' }}</td>
            <td class="font-mono text-center">
              @if ($analysis->measurements_count > 0)
                {{ $analysis->measurements_count }}
                @if ($analysis->non_compliant_count > 0)
                  <span class="badge b-crit ml-1">{{ $analysis->non_compliant_count }}✕</span>
                @endif
              @else
                <span class="text-muted">—</span>
              @endif
            </td>
            <td class="min-w-[120px]">
              @if ($rate !== null)
              <div class="flex gap-1.5 items-center flex-nowrap">
                <div class="flex-1 min-w-[50px] h-[6px] rounded-[3px] bg-line overflow-hidden">
                  <div @class(['h-full rounded-[3px]', 'bg-teal' => $rate === 100, 'bg-warn' => $rate < 100 && $rate >= 60, 'bg-crit' => $rate < 60]) style="width:{{ $rate }}%"></div>
                </div>
                <span class="font-mono text-[.8rem] whitespace-nowrap">{{ $rate }}&thinsp;%</span>
              </div>
              @else
              <span class="text-muted">—</span>
              @endif
            </td>
            <td>
              <x-status-badge :value="$analysis->status->label()" :variant="$analysis->status->badge()" dot />
            </td>
            <td>
              <div class="flex justify-end gap-1.5">
                <a class="btn-icon" href="{{ route('admin.water-analyses.show', $analysis) }}" title="Voir">{!! $eye !!}</a>
                <a class="btn-icon" href="{{ route('admin.water-analyses.edit', $analysis) }}" title="Modifier">{!! $edit !!}</a>
                <button class="btn-icon btn-icon-danger" type="button"
                  data-del="{{ $analysis->reference }}"
                  data-action="{{ route('admin.water-analyses.destroy', $analysis) }}"
                  title="Supprimer">{!! $trash !!}</button>
              </div>
            </td>
          </tr>
          @empty
          <tr>
            <td colspan="9" class="text-muted text-center p-7">
              Aucune analyse ne correspond à ces critères.
            </td>
          </tr>
          @endforelse
        </tbody>
      </table>
    </div>

    <div class="card-footer">
      <span class="text-muted">
        @if ($analyses->total())
          Affichage {{ $analyses->firstItem() }} à {{ $analyses->lastItem() }} sur {{ $analyses->total() }}
        @else
          Aucun résultat
        @endif
      </span>
      {{ $analyses->onEachSide(1)->links('partials.pagination') }}
    </div>
  </div>

</section>
@endsection
