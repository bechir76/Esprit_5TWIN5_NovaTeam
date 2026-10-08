@props(['project'])

{{-- Avancement du financement d'un projet (tableau de bord, financements) : montant via withSum('fundings', 'amount') --}}
<div {{ $attributes->class(['flex flex-col gap-1.5']) }}>
  <div class="flex flex-wrap items-center justify-between gap-2.5">
    <a class="text-[.88rem] font-bold hover:underline" href="{{ route('admin.projects.show', $project) }}">{{ $project->title }}</a>
    <span class="font-mono text-[.8rem] text-muted">{{ $project->progress_percent }} %</span>
  </div>
  <div class="progress-track"><span class="progress-bar" style="width:{{ $project->progress_percent }}%"></span></div>
  <span class="font-mono text-[.76rem] text-muted">{{ \App\Models\Project::money($project->collected_amount) }} / {{ \App\Models\Project::money($project->target_budget) }} TND</span>
</div>
