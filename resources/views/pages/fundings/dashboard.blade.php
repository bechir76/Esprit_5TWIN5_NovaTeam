@extends('layouts.layout')

@section('title', 'Tableau de bord financier')

@section('content')
<section data-page="financements-dashboard" class="flex flex-col gap-3">
  <x-page-header title="Tableau de bord financier" crumb="Console / Financements / Tableau de bord">
    <a class="btn btn-ghost" href="{{ route('admin.fundings.index') }}">Financements</a>
    <a class="btn btn-ghost" href="{{ route('admin.fundings.export') }}">Exporter (CSV)</a>
  </x-page-header>

  <div class="grid gap-[18px] grid-fit-[170px]">
    <x-kpi-card label="Total collecté" :value="\App\Models\Project::money($total)" unit="DT" />
    @foreach (\App\Enums\FundingSource::cases() as $source)
    <x-kpi-card :label="$source->label()" :value="\App\Models\Project::money($bySource[$source->value] ?? 0)" unit="DT" />
    @endforeach
    <x-kpi-card label="Paiements en attente" :value="$pendingCount" />
    <x-kpi-card label="Remboursé" :value="\App\Models\Project::money($refundedTotal)" unit="DT" />
  </div>

  <div class="grid gap-[18px] grid-cols-[repeat(auto-fit,minmax(min(100%,420px),1fr))]">
    <div class="card"><div class="card-header"><h2>Évolution mensuelle</h2><span class="text-[.8rem] text-muted">dons confirmés, 12 mois</span></div>
      <div class="p-[18px] h-[280px]"><canvas id="monthlyChart" data-series="{{ json_encode($monthly) }}" role="img" aria-label="Montant collecté par mois"></canvas></div>
    </div>
    <div class="card"><div class="card-header"><h2>Top donateurs</h2></div>
      <div class="overflow-x-auto"><table class="data-table"><thead><tr><th>Donateur</th><th>Source</th><th>Dons</th><th class="text-right">Total</th></tr></thead><tbody>
        @forelse ($topDonors as $donor)
        <tr><td>{{ $donor->donor }}</td><td>{{ $donor->source_type->label() }}</td><td class="font-mono">{{ $donor->gifts }}</td><td class="font-mono text-right">{{ \App\Models\Project::money($donor->total) }} DT</td></tr>
        @empty
        <tr><td colspan="4" class="text-muted text-center p-7">Aucun don confirmé.</td></tr>
        @endforelse
      </tbody></table></div>
    </div>
  </div>

  <div class="grid gap-[18px] grid-cols-[repeat(auto-fit,minmax(min(100%,420px),1fr))]">
    <div class="card self-start"><div class="card-header"><h2>Presque financés</h2><span class="text-[.8rem] text-muted">≥ {{ config('projects.almost_funded_percent') }} %</span></div>
      <div class="p-[18px] flex flex-col gap-4">
        @forelse ($almostFunded as $project)
        <x-project-progress :project="$project" />
        @empty
        <p class="text-muted">Aucun projet en collecte au-dessus de {{ config('projects.almost_funded_percent') }} %.</p>
        @endforelse
      </div>
    </div>
    <div class="card self-start"><div class="card-header"><h2>Projets en retard</h2><span class="text-[.8rem] text-muted">objectif non atteint au rythme actuel</span></div>
      <div class="p-[18px] flex flex-col gap-4">
        @forelse ($late as $item)
        <div class="flex flex-col gap-1">
          <x-project-progress :project="$item['project']" />
          <span class="text-[.8rem] text-warn-fg">{{ $item['forecast']['message'] }} Date limite : {{ $item['project']->end_date->format('d/m/Y') }}.</span>
        </div>
        @empty
        <p class="text-muted">Toutes les collectes sont dans les temps.</p>
        @endforelse
      </div>
    </div>
  </div>
</section>
@endsection

@push('scripts')
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js"></script>
<script>
(() => {
  const canvas = document.getElementById('monthlyChart');
  if (!canvas || !window.Chart) return;
  const s = JSON.parse(canvas.dataset.series);
  new Chart(canvas, {
    type: 'bar',
    data: {labels: s.labels, datasets: [{label: 'Collecté (DT)', data: s.totals, backgroundColor: '#1CA7EC', borderRadius: 4}]},
    options: {maintainAspectRatio: false, plugins: {legend: {display: false}}, scales: {y: {beginAtZero: true}}},
  });
})();
</script>
@endpush
