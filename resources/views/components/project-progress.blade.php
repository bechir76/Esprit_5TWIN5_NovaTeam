@props(['project'])

{{-- Avancement du financement d'un projet (tableau de bord, financements) : montant via withSum('fundings', 'amount') --}}
<div {{ $attributes->class(['stack']) }} style="gap:6px"><div class="row between"><a href="{{ route('admin.projects.show', $project) }}" style="font-size:.88rem;font-weight:700;text-decoration:none;color:inherit">{{ $project->title }}</a><span class="mono muted" style="font-size:.8rem">{{ $project->progress_percent }} %</span></div><div class="progress"><span style="width:{{ $project->progress_percent }}%"></span></div><span class="muted mono" style="font-size:.76rem">{{ \App\Models\Project::money($project->collected_amount) }} / {{ \App\Models\Project::money($project->target_budget) }} TND</span></div>
