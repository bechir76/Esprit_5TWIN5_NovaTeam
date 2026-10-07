@props(['project'])

{{-- Carte projet du site citoyen (accueil, /projets) : montant collecté via withSum('fundings', 'amount') --}}
@php
    $background = $project->imageUrl() ? "url('{$project->imageUrl()}') center/cover" : $project->category->gradient();
    $progress = $project->progress_percent;
@endphp

<a {{ $attributes->class(['card', 'proj-card']) }} href="{{ route('projects.show', $project) }}" style="text-decoration:none"><div class="proj-img" style="background:{{ $background }}"><span class="badge {{ $project->category->badge() }}">{{ $project->category->label() }}</span></div><div class="proj-body"><h3>{{ $project->title }}</h3><p class="muted" style="font-size:.9rem">{{ Str::limit($project->description, 110) }}</p><div style="margin-top:auto" class="stack"><div class="progress"><span style="width:{{ $progress }}%"></span></div><div class="money"><span><strong class="mono">{{ \App\Models\Project::money($project->collected_amount) }}</strong> / <span class="mono">{{ \App\Models\Project::money($project->target_budget) }} TND</span></span><span class="muted">{{ $progress }} % · @if ($project->acceptsContributions()){{ $project->days_left }} j @else{{ $project->status->label() }}@endif</span></div></div></div></a>
