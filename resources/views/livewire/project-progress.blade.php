{{-- Composant Livewire ProjectProgress : rafraîchi toutes les 5 secondes (dons confirmés uniquement) --}}
<div class="card flex flex-col gap-3.5 p-[22px]" wire:poll.5s>
  <div><strong class="font-display text-[1.8rem] tabular-nums">{{ \App\Models\Project::money($project->collected_amount) }} DT</strong><div class="text-muted">collectés sur {{ \App\Models\Project::money($project->target_budget) }} DT</div></div>
  <div class="progress-track"><span class="progress-bar transition-[width] duration-500" style="width:{{ $project->progress_percent }}%"></span></div>
  <div class="flex flex-wrap items-center justify-between gap-3 text-[.88rem]">
    <span><strong>{{ $project->progress_percent }} %</strong> financé</span>
    <span><strong>{{ $project->donors_count }}</strong> donateur{{ $project->donors_count > 1 ? 's' : '' }}</span>
    @if ($project->isExpired())<span>Collecte close</span>@else<span><strong>{{ $project->days_left }}</strong> jour{{ $project->days_left > 1 ? 's' : '' }} restant{{ $project->days_left > 1 ? 's' : '' }}</span>@endif
  </div>
</div>
