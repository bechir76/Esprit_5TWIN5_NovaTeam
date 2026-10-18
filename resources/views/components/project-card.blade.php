@props(['project'])

{{-- Carte projet du site citoyen (accueil, /projets) : montant collecté via withCollected() --}}
@php
    $background = $project->imageUrl() ? "url('{$project->imageUrl()}') center/cover" : $project->category->gradient();
    $progress = $project->progress_percent;
@endphp

<a {{ $attributes->class(['card flex flex-col overflow-hidden']) }} href="{{ route('projects.show', $project) }}">
  <div class="relative aspect-video max-w-full" style="background:{{ $background }}"><span class="badge absolute top-3 left-3 {{ $project->category->badge() }}">{{ $project->category->label() }}</span></div>
  <div class="flex flex-1 flex-col gap-3 p-[18px]">
    <h3>{{ $project->title }}</h3>
    <p class="text-[.9rem] text-muted">{{ Str::limit($project->description, 110) }}</p>
    <div class="mt-auto flex flex-col gap-3">
      <div class="progress-track"><span class="progress-bar" style="width:{{ $progress }}%"></span></div>
      <div class="flex justify-between gap-2 text-[.85rem]"><span><strong class="font-mono">{{ \App\Models\Project::money($project->collected_amount) }}</strong> / <span class="font-mono">{{ \App\Models\Project::money($project->target_budget) }} DT</span></span><span class="text-muted">{{ $progress }} % · @if ($project->acceptsContributions()){{ $project->days_left }} j @else{{ $project->status->label() }}@endif</span></div>
    </div>
  </div>
</a>
