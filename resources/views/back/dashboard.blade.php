@extends('layouts.back.app')

@section('title', 'Tableau de bord')

@section('content')
<section data-page="dashboard" class="stack" style="gap:20px">
  <x-page-header title="Tableau de bord">
    <div class="row"><select class="input" style="width:auto" aria-label="Période"><option>30 derniers jours</option><option>Trimestre</option><option>Année 2026</option></select><a class="btn btn-primary" href="{{ route('admin.infrastructures.create') }}">+ Nouvelle infrastructure</a></div>
  </x-page-header>
  @if (isset($lastNonCompliant) && $lastNonCompliant)
  @php
    $p = $worstMeasurement?->parameter instanceof \App\Enums\WaterParameter
        ? $worstMeasurement->parameter
        : ($worstMeasurement ? \App\Enums\WaterParameter::from($worstMeasurement->parameter) : null);
    $val = $worstMeasurement ? rtrim(rtrim(number_format((float) $worstMeasurement->value, 4, ',', ''), '0'), ',') : null;
  @endphp
  <div class="alert alert-crit">
    <strong>Alerte qualité&nbsp;:</strong>
    @if ($p && $val)
      {{ $p->label() }} à {{ $val }}{{ $p->unit() ? ' '.$p->unit() : '' }}
      à {{ $lastNonCompliant->infrastructure->name }} (seuil&nbsp;: {{ $p->thresholdHint() }}).
    @else
      Analyse non conforme détectée à {{ $lastNonCompliant->infrastructure->name }}.
    @endif
    Relevé du {{ $lastNonCompliant->sampled_at->format('d/m/Y H:i') }}.
    <a href="{{ route('admin.water-analyses.show', $lastNonCompliant) }}" style="margin-left:auto;font-weight:600;white-space:nowrap">Voir l'analyse</a>
  </div>
  @endif
  <div class="grid kpis">
    <x-kpi-card label="Incidents ouverts" value="27" delta="+4 vs semaine dernière" trend="up" :spark="[18, 20, 19, 23, 22, 25, 23, 27]" />
    <x-kpi-card label="Incidents critiques" value="3" delta="+2 depuis hier" trend="up" :spark="[1, 0, 1, 1, 2, 1, 1, 3]" color="var(--crit)" />
    <x-kpi-card label="Ouvrages à risque" :value="$atRiskCount" :delta="'dont '.$criticalCount.' critique'.($criticalCount > 1 ? 's' : '')" color="var(--warn)" />
    <x-kpi-card label="Fonds collectés" value="518 900" delta="TND · +12 % ce mois" trend="down" :spark="[310, 340, 365, 390, 420, 455, 490, 519]" color="var(--teal)" />
    <x-kpi-card label="Utilisateurs inscrits" value="4 218" delta="+86 ce mois" trend="down" :spark="[3800, 3870, 3940, 4010, 4060, 4120, 4170, 4218]" />
  </div>
  <div class="grid" style="grid-template-columns:repeat(auto-fit,minmax(min(100%,460px),1fr))">
    <div class="card"><div class="card-h"><h2>Incidents par mois</h2><div class="legend"><span><i style="background:var(--water)"></i>Signalés</span><span><i style="background:var(--teal)"></i>Résolus</span></div></div><div class="card-b"><svg class="chart" id="barChart" viewBox="0 0 560 240" width="100%" role="img" aria-label="Incidents signalés et résolus par mois"></svg></div></div>
    <div class="card"><div class="card-h"><h2>Répartition par type</h2><span class="muted mono" style="font-size:.8rem">27 ouverts</span></div><div class="card-b donut-wrap"><svg width="170" height="170" viewBox="0 0 42 42" id="donut" role="img" aria-label="Répartition des incidents ouverts par type"></svg><ul id="donutLegend"></ul></div></div>
  </div>
  <div class="grid" style="grid-template-columns:repeat(auto-fit,minmax(min(100%,460px),1fr))">
    <div class="card"><div class="card-h"><h2>Derniers incidents</h2><a href="{{ route('admin.incidents.index') }}" class="muted" style="font-size:.84rem">Tout voir</a></div><div class="table-wrap"><table style="min-width:520px"><tbody id="dashInc"></tbody></table></div></div>
    <div class="card"><div class="card-h"><h2>Avancement des projets</h2><a href="{{ route('admin.projects.index') }}" class="muted" style="font-size:.84rem">Tout voir</a></div><div class="card-b stack" style="gap:16px" id="dashProj"></div></div>
  </div>
</section>
@endsection
