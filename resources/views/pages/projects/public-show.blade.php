@extends('layouts.layout')

@section('title', $project->title)

@php
    // Étapes du projet : terminées avant le statut actuel, « now » pour le statut actuel (un projet échoué s'arrête à la collecte)
    $failed = $project->status === \App\Enums\ProjectStatus::Failed;
    $flow = array_values(array_filter($statuses, fn ($s) => $s !== \App\Enums\ProjectStatus::Failed));
    $current = $failed ? 1 : array_search($project->status, $flow, true);
    $steps = [
        'draft' => ['Préparation du projet', 'Diagnostic et chiffrage des travaux'],
        'open' => [$failed ? 'Collecte non aboutie' : 'Collecte des financements', $failed
            ? 'Objectif non atteint au '.$project->end_date->translatedFormat('j F Y').' : dons remboursés'
            : 'Du '.$project->start_date->translatedFormat('j F Y').' au '.$project->end_date->translatedFormat('j F Y')],
        'funded' => ['Objectif de financement atteint', \App\Models\Project::money($project->target_budget).' DT réunis'],
        'in_progress' => ['Travaux de réhabilitation', 'Réalisés par la régie et ses prestataires'],
        'completed' => ['Remise en service et contrôle qualité', 'Dépenses publiées à la clôture'],
    ];
    $citizen = $bySource[\App\Enums\FundingSource::Citoyen->value] ?? null;
    $done = $project->status === \App\Enums\ProjectStatus::Completed;
@endphp

@section('content')
<section class="pt-10 pb-16" data-page="projet">
  <div class="mx-auto max-w-[1200px] px-5">
    <a href="{{ route('projects.index') }}" class="text-muted text-[.9rem]">← Retour aux projets</a>
    <div class="mb-7 flex flex-wrap items-end justify-between gap-4 [&_p]:max-w-[60ch] mt-3.5"><div class="flex flex-col gap-2.5"><div class="flex flex-wrap items-center gap-2"><span class="badge {{ $project->category->badge() }}">{{ $project->category->label() }}</span><x-status-badge :value="$project->status->label()" :variant="$project->status->badge()" /></div><h2>{{ $project->title }}</h2><p class="text-muted">{{ $project->description }}</p></div></div>
    <div class="grid items-start gap-6 min-[981px]:grid-cols-[minmax(0,1fr)_360px]">
      <div class="flex flex-col gap-6">
        <div class="card relative aspect-video min-h-[260px] max-w-full border-0" style="background:{{ $project->imageUrl() ? "url('{$project->imageUrl()}') center/cover" : $project->category->gradient() }}"></div>
        @if ($forecastMessage)
        {{-- Prévision calculée sur les dons des 14 derniers jours (B3) --}}
        <div @class(['callout', 'bg-ok-soft text-ok-fg' => $forecast['on_time'], 'bg-warn-soft text-warn-fg' => ! $forecast['on_time']]) role="status">
          <strong>Prévision :</strong> {{ $forecastMessage }} <span class="text-[.82rem]">(probabilité {{ $forecast['probability'] }})</span>
        </div>
        @endif
        <div class="card p-[22px]">
          <h3 class="mb-3">Le projet</h3>
          <div class="line-item"><span>Ouvrage concerné</span><span class="font-mono">{{ $project->infrastructure ? $project->infrastructure->code.' · '.$project->infrastructure->name : 'Plusieurs sites' }}</span></div>
          <div class="line-item"><span>Zone</span><span>{{ $project->infrastructure?->zone->name ?? '—' }}</span></div>
          <div class="line-item"><span>Début de la collecte</span><span class="font-mono">{{ $project->start_date->format('d/m/Y') }}</span></div>
          <div class="line-item border-b-0"><span>Date limite</span><span class="font-mono">{{ $project->end_date->format('d/m/Y') }}</span></div>
        </div>
        <div class="card p-[22px]">
          <h3 class="mb-3">Budget et financements</h3>
          @foreach ($sources as $source)
            @if ($bySource->has($source->value))
            <div class="line-item"><span>{{ $source->label() }}@if ($source === \App\Enums\FundingSource::Citoyen) ({{ $citizen->contributions }})@endif</span><span class="font-mono">{{ \App\Models\Project::money($bySource[$source->value]->total) }} DT</span></div>
            @endif
          @endforeach
          @if ($bySource->isEmpty())
          <div class="line-item"><span class="text-muted">Aucun financement pour le moment</span><span class="font-mono">0 DT</span></div>
          @endif
          <div class="line-item"><span>Total collecté</span><span class="font-mono">{{ \App\Models\Project::money($project->collected_amount) }} DT</span></div>
          <div class="line-item border-b-0 font-bold"><span>Budget objectif</span><span class="font-mono">{{ \App\Models\Project::money($project->target_budget) }} DT</span></div>
          <p class="mt-2 text-[.8rem] text-muted">Financement « tout ou rien » : si l'objectif n'est pas atteint à la date limite, tous les dons sont remboursés.</p>
        </div>
        <div class="card p-[22px]">
          <h3 class="mb-4">Avancement du projet</h3>
          <div class="timeline">
            @foreach ($flow as $i => $status)
            @continue($failed && $i > 1)
            <div @class(['timeline-item', 'is-done' => $i < $current || $done, 'is-now' => $i === $current && ! $done])><strong>{{ $steps[$status->value][0] }}</strong><div class="text-muted text-[.88rem]">{{ $steps[$status->value][1] }}</div></div>
            @endforeach
          </div>
        </div>
      </div>
      <aside class="flex flex-col gap-5 min-[981px]:sticky min-[981px]:top-[90px]">
        {{-- Progression en temps réel (app/Livewire/ProjectProgress.php) et don par Stripe (app/Livewire/ProjectContribution.php) --}}
        <livewire:project-progress :project="$project->withoutRelations()" />
        <livewire:project-contribution :project="$project->withoutRelations()" />
        <div class="card p-[22px]">
          <div class="mb-2.5 flex items-center justify-between gap-2"><h3>Derniers dons</h3><a class="text-[.82rem] text-muted underline" href="{{ route('projects.transparency') }}">Transparence</a></div>
          @forelse ($project->fundings as $funding)
          <div @class(['line-item', 'border-b-0' => $loop->last])><span>{{ $funding->publicDonorName() }}@if ($funding->is_matching) <span class="badge b-info">cofinancement</span>@endif<br><span class="text-muted font-mono text-[.76rem]">{{ $funding->funded_at->format('d/m/Y') }}</span></span><span class="font-mono">{{ \App\Models\Project::money($funding->amount) }}</span></div>
          @empty
          <p class="text-muted text-[.88rem]">Soyez le premier à soutenir ce projet.</p>
          @endforelse
        </div>
      </aside>
    </div>
  </div>
</section>
@endsection
