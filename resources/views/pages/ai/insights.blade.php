@extends('layouts.layout')

@section('title', 'Analyses IA')

@php
    $swatch = 'mr-1.5 inline-block size-2.5 rounded-[3px] align-[-1px]';
@endphp

@section('content')
<section data-page="ia" class="flex flex-col gap-5">
  <x-page-header title="Analyses IA" />
  <div class="flex flex-wrap items-center justify-between gap-5 rounded-card bg-linear-130 from-navy to-navy-2 p-[22px] text-white">
    <div>
      <h2 class="text-[1.25rem] text-white">Ce que l'IA a détecté cette semaine</h2>
      <p class="mt-1.5 max-w-[62ch] text-[#BFD5E4]">3 anomalies sur le réseau, 41 signalements classés automatiquement et {{ $highRiskCount }} infrastructure{{ $highRiskCount > 1 ? 's' : '' }} en risque élevé ou critique.</p>
    </div>
    <span class="rounded-[7px] bg-white/10 px-2.5 py-1.5 font-mono text-[.78rem] text-[#9FD9FA]">Dernier calcul du risque : {{ $lastRiskRun ? \Illuminate\Support\Carbon::parse($lastRiskRun)->format('d/m/Y H:i') : 'jamais' }}</span>
  </div>

  <div class="grid grid-fit-[460px] gap-[18px]">
    <div class="card">
      <div class="card-header"><h2>Score de risque prédictif</h2><a class="text-[.78rem] text-muted underline" href="{{ route('admin.infrastructures.index', ['sort' => 'risk']) }}">Les 5 ouvrages les plus à risque (score sur 100)</a></div>
      @include('partials.riskiest-infrastructures')
    </div>
    <div class="card">
      <div class="card-header"><h2>Détection d'anomalies</h2><span class="badge b-crit dot">Pression · Ariana Nord</span></div>
      <div class="p-[18px]">
        <svg class="w-full" id="anomChart" viewBox="0 0 520 220" width="100%" role="img" aria-label="Pression mesurée sur 24 heures avec bande attendue"></svg>
        <div class="mt-2 flex flex-wrap gap-3.5 text-[.78rem] text-muted">
          <span><i class="{{ $swatch }} bg-water"></i>Pression mesurée (bar)</span>
          <span><i class="{{ $swatch }} border border-line bg-water-soft"></i>Plage attendue</span>
          <span><i class="{{ $swatch }} bg-crit"></i>Anomalie</span>
        </div>
      </div>
    </div>
  </div>

  <div class="grid grid-fit-[460px] gap-[18px]">
    <div class="card">
      <div class="card-header"><h2>Classification automatique des signalements</h2><span class="font-mono text-[.8rem] text-muted">précision 92 %</span></div>
      <div class="overflow-x-auto"><table class="data-table min-w-[480px]"><thead><tr><th>Extrait du signalement</th><th>Catégorie</th><th>Confiance</th></tr></thead><tbody>
        <tr><td>« de l'eau sort du trottoir depuis ce matin »</td><td><span class="badge b-info">Fuite</span></td><td class="font-mono">96 %</td></tr>
        <tr><td>« l'eau est marron et sent l'œuf pourri »</td><td><span class="badge b-crit">Contamination</span></td><td class="font-mono">93 %</td></tr>
        <tr><td>« plus d'eau depuis 2 jours au 3e étage »</td><td><span class="badge b-warn">Coupure</span></td><td class="font-mono">88 %</td></tr>
        <tr><td>« bruit de sifflement dans le compteur »</td><td><span class="badge b-info">Fuite</span></td><td class="font-mono">61 %</td></tr>
      </tbody></table></div>
    </div>
    <div class="card">
      <div class="card-header"><h2>Priorisation recommandée des interventions</h2></div>
      <div class="p-[18px]">
        @foreach ([
            ['Forage de Mornag F3', 'Nitrate au-dessus du seuil, 1 850 foyers exposés. Prélèvements et citerne mobile aujourd\'hui.'],
            ['Conduite DN 300 Ariana Nord', 'Chute de pression nocturne typique d\'une rupture. 5 signalements groupés à moins de 200 m.'],
            ['Réservoir de La Soukra', 'Risque de défaillance de 74 % à 90 jours. Avancer l\'inspection du radier.'],
        ] as [$site, $advice])
        <div class="flex gap-3 border-b border-line py-3.5 last:border-b-0">
          <span class="grid size-7 flex-none place-items-center rounded-full bg-water-soft font-mono text-[.8rem] font-medium text-info-fg">{{ $loop->iteration }}</span>
          <div><strong>{{ $site }}</strong><p class="text-muted">{{ $advice }}</p></div>
        </div>
        @endforeach
      </div>
    </div>
  </div>
</section>
@endsection
