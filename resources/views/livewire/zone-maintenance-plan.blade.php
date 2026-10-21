{{-- Composant Livewire ZoneMaintenancePlan : ouvrages choisis et classés par le code (MaintenancePlanner),
     actions et justifications rédigées par l'IA ou par des règles. Sans JavaScript : route POST admin.zones.maintenance-plan. --}}
@php($plan = $zone->maintenance_plan)
<div class="card">
  <div class="card-header">
    <h2>Plan de maintenance</h2>
    <div class="flex flex-wrap items-center gap-2">
      @if ($plan)<a class="btn btn-ghost btn-sm" href="{{ route('admin.zones.maintenance-plan.pdf', $zone) }}">PDF</a>@endif
      @can('planMaintenance', $zone)
      <form method="POST" action="{{ route('admin.zones.maintenance-plan', $zone) }}" wire:submit="generate">
        @csrf
        <button class="btn btn-primary btn-sm" type="submit" wire:loading.attr="disabled" wire:target="generate">
          <span wire:loading.remove wire:target="generate">{{ $plan ? 'Régénérer le plan' : 'Générer un plan de maintenance' }}</span>
          <span wire:loading wire:target="generate">Génération en cours…</span>
        </button>
      </form>
      @endcan
    </div>
  </div>
  <div class="flex flex-col gap-3 p-[18px]">
    @if ($message)<p class="callout callout-info text-[.86rem]" role="status">{{ $message }}</p>@endif
    @error('plan')<p class="text-[.86rem] text-crit-fg" role="alert">{{ $message }}</p>@enderror

    @if ($plan)
    <p>{{ $plan['summary'] }}</p>
    <ol class="flex flex-col">
      @foreach ($plan['actions'] as $action)
      <li class="flex gap-3 border-b border-line py-3 last:border-b-0">
        <span class="grid size-7 flex-none place-items-center rounded-full bg-water-soft font-mono text-[.8rem] font-medium text-info-fg">{{ $action['rank'] }}</span>
        <div class="flex flex-col gap-1">
          <div><a class="font-semibold" href="{{ route('admin.infrastructures.show', $action['id']) }}">{{ $action['name'] }}</a> <span class="font-mono text-[.76rem] text-muted">{{ $action['code'] }}</span>
            @if ($action['risk_score'] !== null)<span class="ml-1 font-mono text-[.76rem] text-muted">· risque {{ $action['risk_score'] }}/100</span>@endif</div>
          <strong class="text-[.92rem]">{{ $action['action'] }}</strong>
          <p class="text-[.86rem] text-muted">{{ $action['justification'] }}</p>
        </div>
      </li>
      @endforeach
    </ol>
    <span class="text-[.78rem] text-muted">Généré le {{ $zone->maintenance_plan_at?->format('d/m/Y à H:i') }} · texte {{ $zone->maintenance_plan_source === 'ia' ? 'rédigé par l\'IA' : 'généré par des règles' }} · ouvrages classés par le code (risque, retard d'inspection, incidents ouverts, état).</span>
    @else
    <p class="text-muted">Aucun plan généré pour cette zone. Le plan classe les ouvrages selon leur score de risque, le retard d'inspection et les incidents ouverts, puis propose les {{ config('infrastructures.maintenance_plan_size') }} actions prioritaires.</p>
    @endif
  </div>
</div>
