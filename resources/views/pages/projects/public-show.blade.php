@extends('layouts.layout')

@section('title', $project->title)

@php
    // Étapes du projet (statuts dans l'ordre) : terminées avant le statut actuel, « now » pour le statut actuel
    $current = array_search($project->status, $statuses, true);
    $steps = [
        'brouillon' => ['Préparation du projet', 'Diagnostic et chiffrage des travaux'],
        'en_collecte' => ['Collecte des financements', 'Du '.$project->start_date->translatedFormat('j F Y').' au '.$project->end_date->translatedFormat('j F Y')],
        'finance' => ['Objectif de financement atteint', \App\Models\Project::money($project->target_budget).' TND réunis'],
        'en_travaux' => ['Travaux de réhabilitation', 'Réalisés par la régie et ses prestataires'],
        'termine' => ['Remise en service et contrôle qualité', 'Dépenses publiées à la clôture'],
    ];
    $citizen = $bySource[\App\Enums\FundingSource::Citoyen->value] ?? null;
@endphp

@section('content')
<section class="pt-10 pb-16" data-page="projet">
  <div class="mx-auto max-w-[1200px] px-5">
    <a href="{{ route('projects.index') }}" class="text-muted text-[.9rem]">← Retour aux projets</a>
    <div class="mb-7 flex flex-wrap items-end justify-between gap-4 [&_p]:max-w-[60ch] mt-3.5"><div class="flex flex-col gap-2.5"><div class="flex flex-wrap items-center gap-2"><span class="badge {{ $project->category->badge() }}">{{ $project->category->label() }}</span><span class="badge {{ $project->status->badge() }}">{{ $project->status->label() }}</span></div><h2>{{ $project->title }}</h2><p class="text-muted">{{ $project->description }}</p></div></div>
    <div class="grid items-start gap-6 min-[981px]:grid-cols-[minmax(0,1fr)_360px]">
      <div class="flex flex-col gap-6">
        <div class="card relative aspect-video min-h-[260px] max-w-full border-0" style="background:{{ $project->imageUrl() ? "url('{$project->imageUrl()}') center/cover" : $project->category->gradient() }}"></div>
        <div class="card p-[22px]">
          <h3 class="mb-3">Le projet</h3>
          <div class="line-item"><span>Ouvrage concerné</span><span class="font-mono">{{ $project->infrastructure ? $project->infrastructure->code.' · '.$project->infrastructure->name : 'Plusieurs sites' }}</span></div>
          <div class="line-item"><span>Zone</span><span>{{ $project->infrastructure?->zone->name ?? '—' }}</span></div>
          <div class="line-item"><span>Début de la collecte</span><span class="font-mono">{{ $project->start_date->format('d/m/Y') }}</span></div>
          <div class="line-item border-b-0"><span>Fin de la collecte</span><span class="font-mono">{{ $project->end_date->format('d/m/Y') }}</span></div>
        </div>
        <div class="card p-[22px]">
          <h3 class="mb-3">Budget et financements</h3>
          @foreach ($sources as $source)
            @if ($bySource->has($source->value))
            <div class="line-item"><span>{{ $source->label() }}@if ($source === \App\Enums\FundingSource::Citoyen) ({{ $citizen->contributions }})@endif</span><span class="font-mono">{{ \App\Models\Project::money($bySource[$source->value]->total) }} TND</span></div>
            @endif
          @endforeach
          @if ($bySource->isEmpty())
          <div class="line-item"><span class="text-muted">Aucun financement pour le moment</span><span class="font-mono">0 TND</span></div>
          @endif
          <div class="line-item"><span>Total collecté</span><span class="font-mono">{{ \App\Models\Project::money($project->collected_amount) }} TND</span></div>
          <div class="line-item border-b-0 font-bold"><span>Budget objectif</span><span class="font-mono">{{ \App\Models\Project::money($project->target_budget) }} TND</span></div>
        </div>
        <div class="card p-[22px]">
          <h3 class="mb-4">Avancement du projet</h3>
          <div class="timeline">
            @foreach ($statuses as $i => $status)
            <div @class(['timeline-item', 'is-done' => $i < $current || $project->status === \App\Enums\ProjectStatus::Termine, 'is-now' => $i === $current && $project->status !== \App\Enums\ProjectStatus::Termine])><strong>{{ $steps[$status->value][0] }}</strong><div class="text-muted text-[.88rem]">{{ $steps[$status->value][1] }}</div></div>
            @endforeach
          </div>
        </div>
      </div>
      <aside class="flex flex-col gap-5 min-[981px]:sticky min-[981px]:top-[90px]">
        {{-- Contribution sans rechargement : composant Livewire (app/Livewire/ProjectContribution.php) --}}
        <livewire:project-contribution :project="$project->withoutRelations()" />
        <div class="card p-[22px]">
          <h3 class="mb-2.5">Derniers financements</h3>
          @forelse ($project->fundings->take(8) as $funding)
          {{-- L'identité des citoyens contributeurs n'est pas publiée --}}
          <div @class(['line-item', 'border-b-0' => $loop->last])><span>{{ $funding->source_type === \App\Enums\FundingSource::Citoyen ? 'Contribution citoyenne' : $funding->donor_name }}<br><span class="text-muted font-mono text-[.76rem]">{{ $funding->funded_at->format('d/m/Y') }}</span></span><span class="font-mono">{{ \App\Models\Project::money($funding->amount) }}</span></div>
          @empty
          <p class="text-muted text-[.88rem]">Soyez le premier à soutenir ce projet.</p>
          @endforelse
        </div>
      </aside>
    </div>
  </div>
</section>
@endsection
