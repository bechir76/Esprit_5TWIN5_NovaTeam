{{-- Composant Livewire ProjectTable : liste admin des projets, mise à jour à la saisie et au changement de filtre.
     Les boutons de suppression ouvrent la modale <x-confirm-delete /> du layout (data-del + data-action). --}}
@php
    $icon = fn (string $paths) => '<svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round">'.$paths.'</svg>';
    $money = $icon('<circle cx="12" cy="12" r="9"/><path d="M15 9h-4a2 2 0 0 0 0 4h2a2 2 0 0 1 0 4H9M12 6v2M12 16v2"/>');
    $eye = $icon('<path d="M2 12s3.5-7 10-7 10 7 10 7-3.5 7-10 7S2 12 2 12Z"/><circle cx="12" cy="12" r="3"/>');
    $edit = $icon('<path d="M12 20h9M16.5 3.5a2.1 2.1 0 0 1 3 3L7 19l-4 1 1-4Z"/>');
    $trash = $icon('<path d="M3 6h18M8 6V4h8v2M19 6l-1 14H6L5 6"/>');
@endphp

<div class="card">
  {{-- Sans JavaScript, le formulaire GET recharge la page avec les mêmes paramètres --}}
  <form class="filter-bar" method="GET" action="{{ route('admin.projects.index') }}" x-on:submit.prevent>
    <input class="input min-w-[200px] flex-1" type="search" name="q" wire:model.live.debounce.300ms="q" placeholder="Rechercher un projet…" aria-label="Rechercher">
    <select class="input" name="status" wire:model.live="status" aria-label="Statut">
      <option value="">Tous les statuts</option>
      @foreach (\App\Enums\ProjectStatus::cases() as $case)
      <option value="{{ $case->value }}">{{ $case->label() }}</option>
      @endforeach
    </select>
    <select class="input" name="category" wire:model.live="category" aria-label="Catégorie">
      <option value="">Toutes les catégories</option>
      @foreach (\App\Enums\ProjectCategory::cases() as $case)
      <option value="{{ $case->value }}">{{ $case->label() }}</option>
      @endforeach
    </select>
    <select class="input" name="zone" wire:model.live="zone" aria-label="Zone">
      <option value="">Toutes les zones</option>
      @foreach ($zones as $id => $name)
      <option value="{{ $id }}">{{ $name }}</option>
      @endforeach
    </select>
    <select class="input" name="funded" wire:model.live="funded" aria-label="Financé à au moins">
      <option value="">Tout avancement</option>
      @foreach ([25, 50, 80, 100] as $percent)
      <option value="{{ $percent }}">≥ {{ $percent }} %</option>
      @endforeach
    </select>
    <input class="input" type="date" name="before" wire:model.live="before" aria-label="Date limite au plus tard" title="Date limite au plus tard">
    <select class="input" name="sort" wire:model.live="sort" aria-label="Trier">
      @foreach ($sorts as $value => $label)
      <option value="{{ $value }}">{{ $label }}</option>
      @endforeach
    </select>
    <noscript><button class="btn btn-ghost" type="submit">Filtrer</button></noscript>
    @if ($filtered)
    <button class="btn btn-ghost" type="button" wire:click="resetFilters">Réinitialiser</button>
    @endif
    <span class="self-center text-[.8rem] text-muted" wire:loading.delay>Recherche…</span>
  </form>

  <div class="overflow-x-auto transition-opacity" wire:loading.class="opacity-60">
    <table class="data-table"><thead><tr><th>Projet</th><th>Infrastructure</th><th>Budget</th><th>Financement</th><th>Échéance</th><th>Priorité</th><th>Statut</th><th></th></tr></thead><tbody>
      @forelse ($projects as $project)
      @php([$priorityLabel, $priorityBadge] = \App\Services\ProjectScoring::level($project->ai_priority_score))
      <tr wire:key="project-{{ $project->id }}">
        <td><a href="{{ route('admin.projects.show', $project) }}"><strong>{{ $project->title }}</strong></a><br><span class="text-muted text-[.78rem]">{{ $project->category->label() }}@unless ($project->is_public) · non publié @endunless</span></td>
        <td>@if ($project->infrastructure)<span class="font-mono text-[.8rem]">{{ $project->infrastructure->code }}</span><br><span class="text-muted text-[.78rem]">{{ $project->infrastructure->zone->name }}</span>@else<span class="text-muted">Multi-sites</span>@endif</td>
        <td class="font-mono">{{ \App\Models\Project::money($project->target_budget) }} DT</td>
        <td class="min-w-[160px]"><div class="progress-track"><span class="progress-bar" style="width:{{ $project->progress_percent }}%"></span></div><span class="font-mono text-muted text-[.76rem]">{{ \App\Models\Project::money($project->collected_amount) }} · {{ $project->progress_percent }} % · {{ $project->donors_count }} don(s)</span></td>
        <td class="font-mono">{{ $project->end_date->format('d/m/Y') }}</td>
        <td><x-status-badge :value="$project->ai_priority_score !== null ? $project->ai_priority_score.'/100' : '—'" :variant="$priorityBadge" :title="$priorityLabel" /></td>
        <td><x-status-badge :value="$project->status->label()" :variant="$project->status->badge()" /></td>
        <td><div class="flex justify-end gap-1.5">
          <a class="btn-icon" href="{{ route('admin.fundings.index', ['projet' => $project->id]) }}" title="Financements">{!! $money !!}</a>
          <a class="btn-icon" href="{{ route('admin.projects.show', $project) }}" title="Voir">{!! $eye !!}</a>
          <a class="btn-icon" href="{{ route('admin.projects.edit', $project) }}" title="Modifier">{!! $edit !!}</a>
          <button class="btn-icon btn-icon-danger" type="button" data-del="{{ $project->title }}" data-action="{{ route('admin.projects.destroy', $project) }}" title="Supprimer">{!! $trash !!}</button>
        </div></td>
      </tr>
      @empty
      <tr><td colspan="8" class="text-muted text-center p-7">Aucun projet ne correspond à ces critères.</td></tr>
      @endforelse
    </tbody></table>
  </div>

  <div class="card-footer">
    <span class="text-muted">@if ($projects->total())Affichage {{ $projects->firstItem() }} à {{ $projects->lastItem() }} sur {{ $projects->total() }}@else Aucun résultat @endif</span>
    {{ $projects->onEachSide(1)->links('partials.livewire-pagination') }}
  </div>
</div>
