{{-- Composant Livewire InfrastructureRisk : score calculé par le code (RiskScorer), texte rédigé par l'IA ou par des règles.
     Sans JavaScript, le formulaire poste sur la route admin.infrastructures.risk. --}}
@php
    $level = $infrastructure->risk_level;
    $factors = collect($infrastructure->risk_factors ?? [])->sortByDesc('points');
@endphp
<div class="card p-[18px] flex flex-col gap-3">
  <div class="flex items-center justify-between gap-2">
    <h2>Score de risque</h2>
    @if ($level)<x-status-badge :value="'Risque '.mb_strtolower($level->label())" :variant="$level->badge()" dot />@endif
  </div>

  @if ($infrastructure->risk_score !== null)
  <div class="flex items-end gap-1.5"><strong class="font-mono text-[2rem] leading-none">{{ $infrastructure->risk_score }}</strong><span class="text-muted">/ 100</span></div>
  <div class="progress-track" role="img" aria-label="Score de risque {{ $infrastructure->risk_score }} sur 100"><span class="progress-bar" style="width:{{ $infrastructure->risk_score }}%;background:{{ $level->color() }}"></span></div>
  <p class="text-[.9rem]">{{ $infrastructure->risk_summary }}</p>
  <div>
    <span class="caption block">Recommandations</span>
    <ol class="mt-1.5 list-decimal pl-5 text-[.9rem] flex flex-col gap-1">
      @foreach ($infrastructure->risk_recommendations ?? [] as $recommendation)
      <li>{{ $recommendation }}</li>
      @endforeach
    </ol>
  </div>
  <details class="text-[.86rem]">
    <summary class="cursor-pointer text-muted">Détail du calcul</summary>
    <ul class="mt-2 flex flex-col gap-1.5">
      @foreach ($factors as $factor)
      <li class="flex justify-between gap-3"><span>{{ \Illuminate\Support\Str::ucfirst($factor['label']) }}</span><span class="font-mono whitespace-nowrap">{{ number_format($factor['points'], 1, ',', ' ') }} pt</span></li>
      @endforeach
    </ul>
  </details>
  <span class="text-[.78rem] text-muted">Calculé le {{ $infrastructure->risk_computed_at?->format('d/m/Y à H:i') }} · texte {{ $infrastructure->risk_source === 'ia' ? 'rédigé par l\'IA' : 'généré par des règles' }}</span>
  @else
  <p class="text-muted">Ouvrage pas encore évalué.</p>
  @endif

  @if ($message)
  <p class="callout callout-info text-[.86rem]" role="status">{{ $message }}</p>
  @endif
  @error('risk')<p class="text-[.86rem] text-crit-fg" role="alert">{{ $message }}</p>@enderror

  @can('assessRisk', $infrastructure)
  <form method="POST" action="{{ route('admin.infrastructures.risk', $infrastructure) }}" wire:submit="recompute">
    @csrf
    <button class="btn btn-ghost w-full" type="submit" wire:loading.attr="disabled" wire:target="recompute">
      <span wire:loading.remove wire:target="recompute">Recalculer</span>
      <span wire:loading wire:target="recompute">Calcul en cours…</span>
    </button>
  </form>
  @endcan
</div>
