@extends('layouts.layout')

@section('title', 'Tableau de bord')

@section('content')
<section data-page="dashboard" class="flex flex-col gap-5">
  <x-page-header title="Tableau de bord">
    <select class="input w-auto" aria-label="Période"><option>30 derniers jours</option><option>Trimestre</option><option>Année 2026</option></select>
    <a class="btn btn-primary" href="{{ route('admin.infrastructures.create') }}">+ Nouvelle infrastructure</a>
  </x-page-header>
  @if (isset($lastNonCompliant) && $lastNonCompliant)
  @php
    $p = $worstMeasurement?->parameter instanceof \App\Enums\WaterParameter
        ? $worstMeasurement->parameter
        : ($worstMeasurement ? \App\Enums\WaterParameter::from($worstMeasurement->parameter) : null);
    $val = $worstMeasurement ? rtrim(rtrim(number_format((float) $worstMeasurement->value, 4, ',', ''), '0'), ',') : null;
  @endphp
  <div class="callout callout-crit">
    <strong>Alerte qualité&nbsp;:</strong>
    @if ($p && $val)
      {{ $p->label() }} à {{ $val }}{{ $p->unit() ? ' '.$p->unit() : '' }}
      à {{ $lastNonCompliant->infrastructure->name }} (seuil&nbsp;: {{ $p->thresholdHint() }}).
    @else
      Analyse non conforme détectée à {{ $lastNonCompliant->infrastructure->name }}.
    @endif
    Relevé du {{ $lastNonCompliant->sampled_at->format('d/m/Y H:i') }}.
    <a class="ml-auto font-semibold whitespace-nowrap underline" href="{{ route('admin.water-analyses.show', $lastNonCompliant) }}">Voir l'analyse</a>
  </div>
  @endif

  <div class="grid grid-fit-[170px] gap-[18px]">
    <x-kpi-card label="Incidents ouverts" :value="$openIncidents" :delta="'+'.$newIncidentsThisWeek.' signalé'.($newIncidentsThisWeek > 1 ? 's' : '').' en 7 jours'" :trend="$newIncidentsThisWeek > 0 ? 'up' : null" :spark="$incidentSpark" />
    <x-kpi-card label="Incidents critiques" :value="$criticalIncidents" :delta="'+'.$newCriticalToday.' depuis hier'" :trend="$newCriticalToday > 0 ? 'up' : null" :spark="$criticalSpark" color="var(--color-crit)" />
    <x-kpi-card label="Ouvrages à risque" :value="$atRiskCount" :delta="'dont '.$criticalCount.' critique'.($criticalCount > 1 ? 's' : '')" color="var(--color-warn)" />
    <x-kpi-card label="Inspections en retard" :value="$overdueInspections" :delta="$overdueInspections ? 'à planifier en priorité' : 'plan d’inspections à jour'" :trend="$overdueInspections ? 'up' : null" color="var(--color-crit)" />
    <x-kpi-card label="Fonds collectés" :value="\App\Models\Project::money(round($fundsCollected))" :delta="'TND · +'.\App\Models\Project::money(round($fundsThisMonth)).' ce mois'" trend="down" :spark="$fundsSpark" color="var(--color-teal)" />
    <x-kpi-card label="Utilisateurs inscrits" value="4 218" delta="+86 ce mois" trend="down" :spark="[3800, 3870, 3940, 4010, 4060, 4120, 4170, 4218]" />
  </div>

  <div class="grid grid-fit-[460px] gap-[18px]">
    <div class="card">
      <div class="card-header">
        <h2>Incidents par mois</h2>
        <div class="flex flex-wrap gap-3.5 text-[.78rem] text-muted">
          <span><i class="mr-1.5 inline-block size-2.5 rounded-[3px] bg-water align-[-1px]"></i>Signalés</span>
          <span><i class="mr-1.5 inline-block size-2.5 rounded-[3px] bg-teal align-[-1px]"></i>Résolus</span>
        </div>
      </div>
      <div class="p-[18px]"><svg class="w-full" id="barChart" viewBox="0 0 560 240" width="100%" role="img" aria-label="Incidents signalés et résolus par mois" data-series="{{ json_encode($incidentsPerMonth) }}"></svg></div>
    </div>
    <div class="card">
      <div class="card-header"><h2>Répartition par type</h2><span class="font-mono text-[.8rem] text-muted">{{ $openIncidents }} ouvert{{ $openIncidents > 1 ? 's' : '' }}</span></div>
      <div class="flex flex-wrap items-center gap-[18px] p-[18px]">
        <svg width="170" height="170" viewBox="0 0 42 42" id="donut" role="img" aria-label="Répartition des incidents ouverts par type" data-series="{{ json_encode($incidentsByType) }}"></svg>
        <ul class="flex min-w-[150px] flex-1 flex-col gap-[9px]" id="donutLegend"></ul>
      </div>
    </div>
  </div>

  <div class="card">
    <div class="card-header"><h2>Ouvrages les plus à risque</h2><a class="text-[.84rem] text-muted underline" href="{{ route('admin.infrastructures.index', ['sort' => 'risk']) }}">Tout voir</a></div>
    @include('partials.riskiest-infrastructures')
  </div>

  <div class="grid grid-fit-[460px] gap-[18px]">
    <div class="card">
      <div class="card-header"><h2>Derniers incidents</h2><a class="text-[.84rem] text-muted underline" href="{{ route('admin.incidents.index') }}">Tout voir</a></div>
      <div class="overflow-x-auto"><table class="data-table min-w-[520px] [&_td]:border-t-0 [&_td]:border-b"><tbody>
        @forelse ($latestIncidents as $incident)
        <tr>
          <td><span class="mr-2.5 inline-block h-7 w-1 rounded-[2px] align-middle" style="background:{{ $incident->severity->color() }}"></span><span class="inline-block align-middle"><a class="font-semibold" href="{{ route('admin.incidents.show', $incident) }}">{{ $incident->title }}</a><br><span class="text-[.78rem] text-muted">{{ $incident->infrastructure?->name ?? $incident->address }}</span></span></td>
          <td><x-status-badge :value="$incident->status->label()" :variant="$incident->status->badge()" /></td>
          <td class="text-right font-mono text-[.8rem] text-muted">{{ $incident->created_at->format('d/m H:i') }}</td>
        </tr>
        @empty
        <tr><td class="p-7 text-center text-muted">Aucun incident signalé.</td></tr>
        @endforelse
      </tbody></table></div>
    </div>
    <div class="card">
      <div class="card-header"><h2>Avancement des projets</h2><a class="text-[.84rem] text-muted underline" href="{{ route('admin.projects.index') }}">Tout voir</a></div>
      <div class="flex flex-col gap-4 p-[18px]" id="dashProj">
        @forelse ($dashboardProjects as $project)
        <x-project-progress :project="$project" />
        @empty
        <p class="text-muted">Aucun projet en cours.</p>
        @endforelse
      </div>
    </div>
  </div>
</section>
@endsection
