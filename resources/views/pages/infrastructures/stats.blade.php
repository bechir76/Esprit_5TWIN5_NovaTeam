@extends('layouts.layout')

{{-- Statistiques du réseau (InfrastructureStatsService, cache court) : graphiques Chart.js comme le module incidents. --}}
@section('title', 'Statistiques du réseau')

@section('content')
<section data-page="infrastructures-stats" class="flex flex-col gap-3">
  <x-page-header title="Statistiques du réseau" crumb="Console / Infrastructures / Statistiques">
    <div class="flex flex-wrap items-center gap-2.5"><a class="btn btn-ghost" href="{{ route('admin.infrastructures.index') }}">Liste et carte</a></div>
  </x-page-header>

  <div class="grid gap-[18px] grid-fit-[200px]">
    <div class="card p-[18px]"><span class="caption block">Ouvrages</span><strong class="font-mono text-2xl">{{ $stats['total'] }}</strong><div class="text-[.78rem] text-muted">dans {{ count($stats['byZone']) }} zone(s)</div></div>
    <div class="card p-[18px]"><span class="caption block">Âge moyen</span><strong class="font-mono text-2xl">{{ $stats['averageAge'] !== null ? number_format($stats['averageAge'], 1, ',', ' ').' ans' : '—' }}</strong><div class="text-[.78rem] text-muted">depuis la mise en service</div></div>
    <div class="card p-[18px]"><span class="caption block">Risque moyen</span><strong class="font-mono text-2xl">{{ $stats['averageRisk'] !== null ? number_format($stats['averageRisk'], 1, ',', ' ').' / 100' : '—' }}</strong><div class="text-[.78rem] text-muted"><a class="underline" href="{{ route('admin.infrastructures.index', ['sort' => 'risk']) }}">les plus à risque</a></div></div>
    <div class="card p-[18px]"><span class="caption block">Inspections en retard</span><strong @class(['font-mono text-2xl', 'text-crit' => $stats['overdue'] > 0])>{{ $stats['overdue'] }}</strong><div class="text-[.78rem] text-muted"><a class="underline" href="{{ route('admin.infrastructures.index', ['inspection' => 'overdue']) }}">voir la liste</a></div></div>
  </div>

  <div class="grid gap-[18px] min-[1101px]:grid-cols-2">
    <div class="card p-[18px]"><h2 class="mb-3">Ouvrages par état</h2><div class="h-[280px]"><canvas id="chartStatus" aria-label="Ouvrages par état"></canvas></div></div>
    <div class="card p-[18px]"><h2 class="mb-3">Ouvrages par type</h2><div class="h-[280px]"><canvas id="chartType" aria-label="Ouvrages par type"></canvas></div></div>
    <div class="card p-[18px]"><h2 class="mb-3">Âge moyen par zone (ans)</h2><div class="h-[280px]"><canvas id="chartAge" aria-label="Âge moyen des ouvrages par zone"></canvas></div></div>
    <div class="card p-[18px]"><h2 class="mb-3">Risque moyen par zone (/100)</h2><div class="h-[280px]"><canvas id="chartRisk" aria-label="Score de risque moyen par zone"></canvas></div></div>
    <div class="card p-[18px] min-[1101px]:col-span-2"><h2 class="mb-3">Inspections en retard par zone</h2><div class="h-[260px]"><canvas id="chartOverdue" aria-label="Inspections en retard par zone"></canvas></div></div>
  </div>

  <div class="card">
    <div class="card-header"><h2>Détail par zone</h2><span class="font-mono text-[.78rem] text-muted">calculé le {{ $stats['computedAt'] }}</span></div>
    <div class="overflow-x-auto"><table class="data-table"><thead><tr><th>Zone</th><th>Ouvrages</th><th>Âge moyen</th><th>Risque moyen</th><th>Inspections en retard</th></tr></thead><tbody>
      @foreach ($stats['byZone'] as $row)
      <tr>
        <td><strong>{{ $row['zone'] }}</strong></td>
        <td class="font-mono">{{ $row['count'] }}</td>
        <td class="font-mono">{{ $row['averageAge'] !== null ? number_format($row['averageAge'], 1, ',', ' ').' ans' : '—' }}</td>
        <td class="font-mono">{{ $row['averageRisk'] !== null ? number_format($row['averageRisk'], 1, ',', ' ') : '—' }}</td>
        <td class="font-mono">{{ $row['overdue'] }}</td>
      </tr>
      @endforeach
    </tbody></table></div>
  </div>
</section>
@endsection

@push('scripts')
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js" integrity="sha384-9nhczxUqK87bcKHh20fSQcTGD4qq5GhayNYSYWqwBkINBhOfQLg/P5HG5lF1urn4" crossorigin="anonymous"></script>
<script>
  (() => {
    if (! window.Chart) return;
    const stats = {{ Js::from($stats) }};
    const options = { responsive: true, maintainAspectRatio: false };
    const zones = stats.byZone.map((z) => z.zone);
    const bar = (id, label, data, color) => new Chart(document.getElementById(id), {
      type: 'bar',
      data: { labels: zones, datasets: [{ label, data, backgroundColor: color }] },
      options: { ...options, plugins: { legend: { display: false } }, scales: { y: { beginAtZero: true } } },
    });

    new Chart(document.getElementById('chartStatus'), {
      type: 'doughnut',
      data: { labels: stats.byStatus.map((s) => s.label), datasets: [{ data: stats.byStatus.map((s) => s.count), backgroundColor: stats.byStatus.map((s) => s.color) }] },
      options,
    });
    new Chart(document.getElementById('chartType'), {
      type: 'bar',
      data: { labels: stats.byType.map((t) => t.label), datasets: [{ label: 'Ouvrages', data: stats.byType.map((t) => t.count), backgroundColor: '#1CA7EC' }] },
      options: { ...options, plugins: { legend: { display: false } }, scales: { y: { beginAtZero: true, ticks: { precision: 0 } } } },
    });
    bar('chartAge', 'Âge moyen (ans)', stats.byZone.map((z) => z.averageAge), '#0B3C5D');
    bar('chartRisk', 'Risque moyen', stats.byZone.map((z) => z.averageRisk), '#F4A261');
    bar('chartOverdue', 'Inspections en retard', stats.byZone.map((z) => z.overdue), '#E63946');
  })();
</script>
@endpush
