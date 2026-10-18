@extends('layouts.layout')

{{-- Tableau de bord du module incidents : indicateurs (IncidentStatsService, cache court), graphiques Chart.js
     et groupes d'incidents liés détectés par incidents:cluster (B4) avec le bouton « Regrouper les interventions ». --}}
@section('title', 'Statistiques des incidents')

@section('content')
<section data-page="incidents-stats" class="flex flex-col gap-3">
  <x-page-header title="Statistiques des incidents" crumb="Console / Incidents / Statistiques">
    <div class="flex flex-wrap items-center gap-2.5"><a class="btn btn-ghost" href="{{ route('admin.incidents.map') }}">Carte</a><a class="btn btn-ghost" href="{{ route('admin.incidents.index') }}">Liste</a></div>
  </x-page-header>

  <div class="grid gap-[18px] grid-fit-[200px]">
    <div class="card p-[18px]"><span class="caption block">Délai cible respecté</span><strong class="font-mono text-2xl">{{ $stats['slaRate'] !== null ? $stats['slaRate'].' %' : '—' }}</strong><div class="text-[.78rem] text-muted">des incidents résolus</div></div>
    <div class="card p-[18px]"><span class="caption block">Incidents en retard</span><strong @class(['font-mono text-2xl', 'text-crit' => $stats['overdueOpen'] > 0])>{{ $stats['overdueOpen'] }}</strong><div class="text-[.78rem] text-muted"><a class="underline" href="{{ route('admin.incidents.index', ['retard' => 1]) }}">voir la liste</a></div></div>
    <div class="card p-[18px]"><span class="caption block">Ouverts sans ouvrage</span><strong class="font-mono text-2xl">{{ $stats['withoutInfrastructure'] }}</strong><div class="text-[.78rem] text-muted"><a class="underline" href="{{ route('admin.incidents.index', ['infrastructure' => 'sans']) }}">à rattacher</a></div></div>
    <div class="card p-[18px]"><span class="caption block">Accord IA / agents</span><strong class="font-mono text-2xl">{{ $stats['aiTypeAgreement'] !== null ? $stats['aiTypeAgreement'].' %' : '—' }}</strong><div class="text-[.78rem] text-muted">type · gravité {{ $stats['aiSeverityAgreement'] !== null ? $stats['aiSeverityAgreement'].' %' : '—' }} ({{ $stats['aiAnalyzed'] }} analysés)</div></div>
  </div>

  {{-- B4 : groupes d'incidents liés (même zone, même période) --}}
  <div class="card">
    <div class="card-header"><h2>Incidents liés ({{ $clusters->count() }} groupe(s))</h2>
      <form method="POST" action="{{ route('admin.incidents.recluster') }}">@csrf<button class="btn btn-ghost btn-sm" type="submit">Recalculer</button></form>
    </div>
    <div class="flex flex-col gap-3 p-[18px]">
      @forelse ($clusters as $key => $group)
      <div class="rounded-[9px] border border-line p-3.5 flex flex-col gap-2">
        <div class="flex flex-wrap items-center justify-between gap-2.5">
          <strong>{{ $group->count() }} incidents proches · <span class="font-mono">{{ $key }}</span></strong>
          <form method="POST" action="{{ route('admin.incidents.group', $key) }}">@csrf<button class="btn btn-primary btn-sm" type="submit">Regrouper les interventions</button></form>
        </div>
        @if ($group->first()->cluster_diagnosis)
        <p class="text-[.88rem]">✦ {{ $group->first()->cluster_diagnosis }}</p>
        @endif
        <div class="flex flex-wrap gap-2 text-[.82rem]">
          @foreach ($group as $incident)
          <a class="rounded bg-surface-2 px-2 py-1 font-mono" href="{{ route('admin.incidents.show', $incident) }}">{{ $incident->reference }} · {{ $incident->type->label() }}</a>
          @endforeach
        </div>
      </div>
      @empty
      <p class="text-muted">Aucun groupe d'incidents liés pour le moment.</p>
      @endforelse
    </div>
  </div>

  <div class="grid gap-[18px] min-[1101px]:grid-cols-2">
    <div class="card p-[18px]"><h2 class="mb-3">Incidents par type (6 derniers mois)</h2><div class="h-[280px]"><canvas id="chartTypes" aria-label="Incidents par type et par mois"></canvas></div></div>
    <div class="card p-[18px]"><h2 class="mb-3">Temps moyen de résolution par gravité (h)</h2><div class="h-[280px]"><canvas id="chartResolution" aria-label="Temps moyen de résolution par gravité"></canvas></div></div>
    <div class="card"><div class="card-header"><h2>Charge des équipes (30 jours)</h2></div>
      <div class="overflow-x-auto"><table class="data-table"><thead><tr><th>Équipe</th><th>Interventions</th><th>Heures prévues</th></tr></thead><tbody>
        @forelse ($stats['teamLoad'] as $row)
        <tr><td>{{ $row['team'] }}</td><td class="font-mono">{{ $row['count'] }}</td><td class="font-mono">{{ $row['hours'] }}</td></tr>
        @empty
        <tr><td colspan="3" class="p-7 text-center text-muted">Aucune intervention ces 30 derniers jours.</td></tr>
        @endforelse
      </tbody></table></div>
    </div>
    <div class="card"><div class="card-header"><h2>Zones les plus touchées</h2></div>
      <div class="overflow-x-auto"><table class="data-table"><thead><tr><th>Zone</th><th>Incidents</th></tr></thead><tbody>
        @forelse ($stats['topZones'] as $zone => $count)
        <tr><td>{{ $zone }}</td><td class="font-mono">{{ $count }}</td></tr>
        @empty
        <tr><td colspan="2" class="p-7 text-center text-muted">Aucune donnée.</td></tr>
        @endforelse
      </tbody></table></div>
    </div>
  </div>
  <p class="text-[.78rem] text-muted">Calculé le {{ $stats['computedAt'] }} (mis en cache quelques minutes).</p>
</section>
@endsection

@push('scripts')
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js"></script>
<script>
  (() => {
    const byType = @json($stats['byTypeMonth']);
    const resolution = @json($stats['resolutionBySeverity']);
    const options = { responsive: true, maintainAspectRatio: false };

    new Chart(document.getElementById('chartTypes'), {
      type: 'bar',
      data: { labels: byType.labels, datasets: byType.series.map((s) => ({ label: s.label, data: s.data, backgroundColor: s.color })) },
      options: { ...options, scales: { x: { stacked: true }, y: { stacked: true, beginAtZero: true, ticks: { precision: 0 } } } },
    });
    new Chart(document.getElementById('chartResolution'), {
      type: 'bar',
      data: { labels: Object.keys(resolution), datasets: [{ label: 'Heures', data: Object.values(resolution), backgroundColor: ['#2EC4B6', '#F4A261', '#E76F51', '#E63946'] }] },
      options: { ...options, plugins: { legend: { display: false } }, scales: { y: { beginAtZero: true } } },
    });
  })();
</script>
@endpush
