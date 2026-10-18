@extends('layouts.layout')

@section('title', 'Accueil')

@php
    // Valeurs du gauge-card issues de la dernière analyse validée (BD) ou fallback statique
    use App\Enums\WaterParameter;

    $chlore = $latestMeasurements->get('chlore');
    $ph     = $latestMeasurements->get('ph');
    $turb   = $latestMeasurements->get('turbidite');

    $fmtVal = function ($m, int $dec = 2) {
        if (! $m) return '—';
        return number_format((float) $m->value, $dec, ',', ' ');
    };

    $allCompliant = $latestAnalysis && $latestAnalysis->compliance_rate === 100;
    $badgeClass   = $allCompliant ? 'bg-teal/18 text-[#7BE8DC]' : 'bg-crit/18 text-[#FF8A93]';
    $badgeLabel   = $allCompliant ? 'Conforme' : 'Non conforme';

    // Lignes du gauge-card et étapes « Comment ça marche »
    $gaugeRow = 'flex justify-between border-b border-dashed border-white/15 py-2.5 text-[.92rem] last:border-b-0';
    $steps = [
        ['Vous signalez', 'Photo, position et description. L\'assistant IA propose la catégorie et la gravité.'],
        ['La régie qualifie et intervient', 'Le signalement est vérifié, rattaché à l\'infrastructure concernée et confié à une équipe terrain.'],
        ['Vous suivez jusqu\'à la résolution', 'Chaque changement de statut vous est notifié, jusqu\'à la clôture.'],
    ];
@endphp

@section('content')
<section data-page="accueil">
  <div class="relative overflow-hidden bg-hero text-white">
    <canvas class="pointer-events-none absolute inset-0 size-full opacity-35" id="ripple" aria-hidden="true"></canvas>
    <div class="mx-auto grid max-w-[1200px] items-center gap-10 px-5 pt-16 pb-[72px] min-[901px]:grid-cols-[1.1fr_.9fr] [&>*]:relative">
      <div>
        <span class="kicker text-[#7FD8FF]">Réseau d'eau potable · Grand Tunis</span>
        <h1 class="mt-3.5">Une eau sûre se surveille ensemble.</h1>
        <p class="mt-[18px] mb-[26px] max-w-[52ch] text-[1.1rem] text-[#C7DDEB]">Signalez une fuite, une eau trouble ou une coupure en deux minutes. Suivez l'état des réservoirs et des canalisations de votre quartier, et financez les travaux qui comptent.</p>
        <div class="flex flex-wrap items-center gap-3">
          <a class="btn btn-primary" href="{{ route('incidents.create') }}">Signaler un incident</a>
          <a class="btn btn-outline-light" href="{{ route('map') }}">Voir la carte du réseau</a>
        </div>
      </div>

      {{-- Gauge-card : données réelles si disponibles, sinon fallback --}}
      <div class="rounded-panel border border-white/16 bg-white/7 p-[22px] backdrop-blur-xs" aria-label="Qualité de l'eau mesurée aujourd'hui">
        <div class="mb-1.5 flex flex-wrap items-center justify-between gap-3">
          <strong>Qualité mesurée aujourd'hui</strong>
          <span class="badge {{ $badgeClass }}">{{ $badgeLabel }}</span>
        </div>
        @if ($latestAnalysis)
        <div class="mb-2 text-[.82rem] text-[#9CBCD1]">
          {{ $latestAnalysis->infrastructure->name }} · relevé {{ $latestAnalysis->sampled_at->format('H:i') }}
          @if ($latestAnalysis->sampling_point)
            · {{ $latestAnalysis->sampling_point }}
          @endif
        </div>
        @else
        <div class="mb-2 text-[.82rem] text-[#9CBCD1]">
          Station de traitement Ghdir El Golla · relevé 08:30
        </div>
        @endif

        <div>
          <div class="{{ $gaugeRow }}">
            <span>Chlore résiduel</span>
            <span class="font-mono text-[#7FD8FF]">{{ $chlore ? $fmtVal($chlore, 2).' mg/L' : '—' }}</span>
          </div>
          <div class="{{ $gaugeRow }}">
            <span>Turbidité</span>
            <span class="font-mono text-[#7FD8FF]">{{ $turb ? $fmtVal($turb, 2).' NTU' : '—' }}</span>
          </div>
          <div class="{{ $gaugeRow }}">
            <span>pH</span>
            <span class="font-mono text-[#7FD8FF]">{{ $ph ? $fmtVal($ph, 1) : '—' }}</span>
          </div>
          <div class="{{ $gaugeRow }}">
            <span>Pression moyenne du réseau</span>
            <span class="font-mono text-[#7FD8FF]">3,1 bar</span>
          </div>
          <div class="{{ $gaugeRow }}">
            <span>Niveau des barrages</span>
            <span class="font-mono text-[#F4C18F]">31 %</span>
          </div>
        </div>
        @if ($latestAnalysis)
        <div class="mt-2.5 border-t border-dashed border-white/15 pt-2.5">
          <a class="text-[.82rem] text-[#7FD8FF] hover:underline" href="{{ route('water-quality') }}">
            Voir toutes les analyses →
          </a>
        </div>
        @endif
      </div>
    </div>
  </div>

  <div class="mx-auto max-w-[1200px] px-5">
    <div class="relative z-[2] -mt-9 grid grid-fit-[200px] gap-px overflow-hidden rounded-card border border-line bg-line [&>div]:bg-surface [&>div]:p-[22px]">
      <div><strong class="block font-display text-[2rem] font-extrabold tabular-nums">{{ number_format($resolvedThisYear, 0, ',', ' ') }}</strong><span class="text-muted">incidents résolus en {{ now()->year }}</span></div>
      <div><strong class="block font-display text-[2rem] font-extrabold tabular-nums">{{ number_format($fundedProjectsCount, 0, ',', ' ') }}</strong><span class="text-muted">projets de rénovation financés</span></div>
      <div><strong class="block font-display text-[2rem] font-extrabold tabular-nums">{{ number_format($infrastructureCount, 0, ',', ' ') }}</strong><span class="text-muted">infrastructures surveillées</span></div>
      <div><strong class="block font-display text-[2rem] font-extrabold tabular-nums">18 h</strong><span class="text-muted">délai moyen d'intervention</span></div>
    </div>

    <div class="mt-16 grid grid-fit-[280px] items-start gap-5">
      <div class="flex flex-col gap-5">
        <span class="kicker">Comment ça marche</span>
        <h2>Du signalement à la réparation</h2>
        <div class="mt-2 flex flex-col gap-[22px]">
          @foreach ($steps as [$title, $text])
          <div class="flex gap-4">
            <span class="grid size-[38px] flex-none place-items-center rounded-full border-2 border-water font-mono font-medium text-water">{{ $loop->iteration }}</span>
            <div><h3>{{ $title }}</h3><p class="text-muted">{{ $text }}</p></div>
          </div>
          @endforeach
        </div>
      </div>
      <div class="card p-[22px]">
        <div class="mb-1.5 flex flex-wrap items-center justify-between gap-3"><h3>Derniers signalements</h3><a class="text-[.88rem] text-muted underline" href="{{ route('incidents.index') }}">Tout voir</a></div>
        <div>
          @forelse ($latestIncidents as $incident)
          <div class="flex items-start gap-3.5 border-b border-line py-4 last:border-b-0">
            <div class="grid size-10 flex-none place-items-center rounded-card" style="background:{{ $incident->type->softColor() }}">{{ $incident->type->icon() }}</div>
            <div class="min-w-0 flex-1"><strong>{{ $incident->title }}</strong><div class="text-[.85rem] text-muted">{{ $incident->infrastructure?->zone->name ?? $incident->address }} · {{ $incident->created_at->diffForHumans() }}</div></div>
            <span class="badge {{ $incident->status->badge() }}">{{ $incident->status->label() }}</span>
          </div>
          @empty
          <p class="py-4 text-muted">Aucun signalement pour le moment.</p>
          @endforelse
        </div>
      </div>
    </div>

    <div class="mt-16">
      <div class="mb-7 flex flex-wrap items-end justify-between gap-4">
        <div class="flex flex-col gap-2"><span class="kicker">Financement participatif</span><h2>Projets en cours de financement</h2></div>
        <a class="btn btn-ghost" href="{{ route('projects.index') }}">Tous les projets</a>
      </div>
      <div class="grid grid-fit-[260px] gap-5" id="homeProjects">
        @forelse ($homeProjects as $project)
        <x-project-card :project="$project" />
        @empty
        <p class="text-muted">Aucun projet en cours de financement pour le moment.</p>
        @endforelse
      </div>
    </div>
  </div>
</section>
@endsection
