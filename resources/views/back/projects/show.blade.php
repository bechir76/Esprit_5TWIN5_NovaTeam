@extends('layouts.back.app')

@section('title', $project->title)

@php
    $icon = fn (string $paths) => '<svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round">'.$paths.'</svg>';
    $eye = $icon('<path d="M2 12s3.5-7 10-7 10 7 10 7-3.5 7-10 7S2 12 2 12Z"/><circle cx="12" cy="12" r="3"/>');
    $edit = $icon('<path d="M12 20h9M16.5 3.5a2.1 2.1 0 0 1 3 3L7 19l-4 1 1-4Z"/>');
    $trash = $icon('<path d="M3 6h18M8 6V4h8v2M19 6l-1 14H6L5 6"/>');
@endphp

@section('content')
<section data-page="projet" class="flex flex-col gap-3">
  <x-page-header :title="$project->title" :crumb="'Console / Projets / '.$project->title">
    <x-slot:badges><x-status-badge :value="$project->category->label()" :variant="$project->category->badge()" /><x-status-badge :value="$project->status->label()" :variant="$project->status->badge()" /></x-slot:badges>
    <div class="flex flex-wrap items-center gap-2.5">
      @if ($project->is_public && $project->status !== \App\Enums\ProjectStatus::Brouillon)
      <a class="btn btn-ghost" href="{{ route('projects.show', $project) }}" target="_blank" rel="noopener">Voir sur le site</a>
      @endif
      <a class="btn btn-ghost" href="{{ route('admin.projects.edit', $project) }}">Modifier</a><button class="btn btn-danger" type="button" data-del="{{ $project->title }}" data-action="{{ route('admin.projects.destroy', $project) }}">Supprimer</button>
    </div>
  </x-page-header>
  <div class="grid items-start gap-[18px] min-[1101px]:grid-cols-[minmax(0,1fr)_340px]">
    <div class="flex flex-col gap-[18px]">
      <div class="card p-[18px] flex flex-col gap-4">
        <div class="grid grid-fit-[170px] gap-4">
          <div><span class="caption block">Catégorie</span><span>{{ $project->category->label() }}</span></div>
          <div><span class="caption block">Infrastructure</span>
            @if ($project->infrastructure)
            <a class="text-info-fg underline" href="{{ route('admin.infrastructures.show', $project->infrastructure) }}">{{ $project->infrastructure->code }} · {{ $project->infrastructure->name }}</a>
            @else
            <span>Multi-sites</span>
            @endif
          </div>
          <div><span class="caption block">Zone</span><span>{{ $project->infrastructure?->zone->name ?? '—' }}</span></div>
          <div><span class="caption block">Début de la collecte</span><span class="font-mono">{{ $project->start_date->format('d/m/Y') }}</span></div>
          <div><span class="caption block">Fin de la collecte</span><span class="font-mono">{{ $project->end_date->format('d/m/Y') }}</span></div>
          <div><span class="caption block">Jours restants</span><span class="font-mono">{{ $project->isExpired() ? 'Échue' : $project->days_left.' j' }}</span></div>
          <div><span class="caption block">Site public</span><span>{{ $project->is_public ? 'Publié' : 'Non publié' }}</span></div>
          <div><span class="caption block">Adresse publique</span><span class="font-mono text-[.8rem]">/projets/{{ $project->slug }}</span></div>
        </div>
        <div><span class="caption block">Description</span><p class="mt-1.5 max-w-[70ch]">{{ $project->description }}</p></div>
        <div><span class="caption block">Image</span>
          @if ($project->image_path)
          <div class="flex flex-wrap gap-2.5 mt-2"><a class="block h-[180px] w-[240px] max-w-full overflow-hidden rounded-lg bg-surface-2" href="{{ $project->imageUrl() }}" target="_blank" rel="noopener"><img class="w-full h-full object-cover" src="{{ $project->imageUrl() }}" alt="Image du projet {{ $project->title }}"></a></div>
          @else
          <p class="text-muted mt-1.5">Aucune image.</p>
          @endif
        </div>
      </div>

      <div class="card">
        <div class="card-header"><h2>Financements</h2><a class="btn btn-primary btn-sm" href="{{ route('admin.fundings.create', ['projet' => $project->id]) }}">+ Ajouter un financement</a></div>
        <div class="overflow-x-auto"><table class="data-table"><thead><tr><th>Date</th><th>Référence</th><th>Source</th><th>Financeur</th><th class="text-right">Montant</th><th></th></tr></thead><tbody>
          @forelse ($project->fundings as $funding)
          <tr>
            <td class="font-mono whitespace-nowrap">{{ $funding->funded_at->format('d/m/Y') }}</td>
            <td><a class="font-mono text-[.82rem]" href="{{ route('admin.fundings.show', $funding) }}">{{ $funding->reference }}</a></td>
            <td><x-status-badge :value="$funding->source_type->label()" :variant="$funding->source_type->badge()" /></td>
            <td>{{ $funding->donor_name }}@if ($funding->user)<br><span class="text-muted text-[.78rem]">{{ $funding->user->email }}</span>@endif</td>
            <td class="font-mono text-right whitespace-nowrap">{{ \App\Models\Project::money($funding->amount) }} TND</td>
            <td><div class="flex justify-end gap-1.5">
              <a class="btn-icon" href="{{ route('admin.fundings.show', $funding) }}" title="Voir">{!! $eye !!}</a>
              <a class="btn-icon" href="{{ route('admin.fundings.edit', $funding) }}" title="Modifier">{!! $edit !!}</a>
              <button class="btn-icon btn-icon-danger" type="button" data-del="{{ $funding->reference }}" data-action="{{ route('admin.fundings.destroy', ['funding' => $funding, 'retour' => 'projet']) }}" title="Supprimer">{!! $trash !!}</button>
            </div></td>
          </tr>
          @empty
          <tr><td colspan="6" class="text-muted text-center p-7">Aucun financement pour ce projet.</td></tr>
          @endforelse
        </tbody></table></div>
      </div>
    </div>
    <aside class="flex flex-col gap-[18px]">
      <div class="card p-[18px] flex flex-col gap-3"><h2>Financement</h2>
        <div><strong class="font-mono text-[1.5rem]">{{ \App\Models\Project::money($project->collected_amount) }} TND</strong><div class="text-muted text-[.86rem]">collectés sur {{ \App\Models\Project::money($project->target_budget) }} TND</div></div>
        <div class="progress-track"><span class="progress-bar" style="width:{{ $project->progress_percent }}%"></span></div>
        <div class="flex flex-wrap items-center gap-2.5 justify-between text-[.86rem]"><span><strong>{{ $project->progress_percent }} %</strong> financé</span><span class="text-muted">{{ $project->fundings->count() }} contribution{{ $project->fundings->count() > 1 ? 's' : '' }}</span></div>
        @if ($project->collected_amount < (float) $project->target_budget)
        <span class="text-[.78rem] text-muted">Reste à financer : {{ \App\Models\Project::money((float) $project->target_budget - $project->collected_amount) }} TND</span>
        @endif
      </div>
      <div class="card p-[18px] flex flex-col gap-3"><h2>Par source</h2>
        <div class="grid grid-fit-[170px] gap-2">
          @foreach (\App\Enums\FundingSource::cases() as $source)
          <div><span class="caption block">{{ $source->label() }}</span><span class="font-mono">{{ \App\Models\Project::money($bySource[$source->value] ?? 0) }} TND</span></div>
          @endforeach
        </div>
      </div>
      @if ($project->infrastructure)
      <div class="card p-[18px] flex flex-col gap-3"><h2>Infrastructure</h2>
        <div class="grid grid-fit-[170px] gap-2">
          <div><span class="caption block">Code</span><span class="font-mono">{{ $project->infrastructure->code }}</span></div>
          <div><span class="caption block">Type</span><span>{{ $project->infrastructure->type->label() }}</span></div>
          <div><span class="caption block">État</span><x-status-badge :value="$project->infrastructure->status->label()" :variant="$project->infrastructure->status->badge()" dot /></div>
        </div>
        <a class="btn btn-ghost justify-center mt-1.5" href="{{ route('admin.infrastructures.show', $project->infrastructure) }}">Voir l'infrastructure</a>
      </div>
      @endif
    </aside>
  </div>
</section>
@endsection
