@extends('layouts.back.app')

@section('title', 'Projets de rénovation')

@php
    // Icônes des actions (mêmes tracés que back.js)
    $icon = fn (string $paths) => '<svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round">'.$paths.'</svg>';
    $money = $icon('<circle cx="12" cy="12" r="9"/><path d="M15 9h-4a2 2 0 0 0 0 4h2a2 2 0 0 1 0 4H9M12 6v2M12 16v2"/>');
    $eye = $icon('<path d="M2 12s3.5-7 10-7 10 7 10 7-3.5 7-10 7S2 12 2 12Z"/><circle cx="12" cy="12" r="3"/>');
    $edit = $icon('<path d="M12 20h9M16.5 3.5a2.1 2.1 0 0 1 3 3L7 19l-4 1 1-4Z"/>');
    $trash = $icon('<path d="M3 6h18M8 6V4h8v2M19 6l-1 14H6L5 6"/>');
@endphp

@section('content')
<section data-page="projets" class="stack">
  <x-page-header title="Projets de rénovation">
    <a class="btn btn-primary" href="{{ route('admin.projects.create') }}">+ Nouveau projet</a>
  </x-page-header>
  <div class="card">
    <form class="filters" method="GET" action="{{ route('admin.projects.index') }}">
      <input class="input grow" type="search" name="q" value="{{ $filters['q'] ?? '' }}" placeholder="Rechercher un projet…" aria-label="Rechercher">
      <select class="input" name="status" aria-label="Statut" onchange="this.form.submit()">
        <option value="">Tous les statuts</option>
        @foreach (\App\Enums\ProjectStatus::cases() as $status)
        <option value="{{ $status->value }}" @selected(($filters['status'] ?? '') === $status->value)>{{ $status->label() }}</option>
        @endforeach
      </select>
      <select class="input" name="category" aria-label="Catégorie" onchange="this.form.submit()">
        <option value="">Toutes les catégories</option>
        @foreach (\App\Enums\ProjectCategory::cases() as $category)
        <option value="{{ $category->value }}" @selected(($filters['category'] ?? '') === $category->value)>{{ $category->label() }}</option>
        @endforeach
      </select>
      <select class="input" name="zone" aria-label="Zone" onchange="this.form.submit()">
        <option value="">Toutes les zones</option>
        @foreach ($zones as $id => $name)
        <option value="{{ $id }}" @selected((string) ($filters['zone'] ?? '') === (string) $id)>{{ $name }}</option>
        @endforeach
      </select>
      <button class="btn btn-ghost" type="submit">Filtrer</button>
      @if (array_filter($filters))
      <a class="btn btn-ghost" href="{{ route('admin.projects.index') }}">Réinitialiser</a>
      @endif
    </form>
    <div class="table-wrap"><table><thead><tr><th>Projet</th><th>Infrastructure</th><th>Budget</th><th>Financement</th><th>Échéance</th><th>Statut</th><th></th></tr></thead><tbody>
      @forelse ($projects as $project)
      <tr>
        <td><a href="{{ route('admin.projects.show', $project) }}" style="text-decoration:none"><strong>{{ $project->title }}</strong></a><br><span class="muted" style="font-size:.78rem">{{ $project->category->label() }}@unless ($project->is_public) · non publié @endunless</span></td>
        <td>@if ($project->infrastructure)<span class="mono" style="font-size:.8rem">{{ $project->infrastructure->code }}</span><br><span class="muted" style="font-size:.78rem">{{ $project->infrastructure->zone->name }}</span>@else<span class="muted">Multi-sites</span>@endif</td>
        <td class="mono">{{ \App\Models\Project::money($project->target_budget) }} TND</td>
        <td style="min-width:160px"><div class="progress"><span style="width:{{ $project->progress_percent }}%"></span></div><span class="mono muted" style="font-size:.76rem">{{ \App\Models\Project::money($project->collected_amount) }} · {{ $project->progress_percent }} %</span></td>
        <td class="mono">{{ $project->end_date->format('d/m/Y') }}</td>
        <td><x-status-badge :value="$project->status->label()" :variant="$project->status->badge()" /></td>
        <td><div class="acts">
          <a class="act" href="{{ route('admin.fundings.index', ['projet' => $project->id]) }}" title="Financements">{!! $money !!}</a>
          <a class="act" href="{{ route('admin.projects.show', $project) }}" title="Voir">{!! $eye !!}</a>
          <a class="act" href="{{ route('admin.projects.edit', $project) }}" title="Modifier">{!! $edit !!}</a>
          <button class="act del" type="button" data-del="{{ $project->title }}" data-action="{{ route('admin.projects.destroy', $project) }}" title="Supprimer">{!! $trash !!}</button>
        </div></td>
      </tr>
      @empty
      <tr><td colspan="7" class="muted" style="text-align:center;padding:28px">Aucun projet ne correspond à ces critères.</td></tr>
      @endforelse
    </tbody></table></div>
    <div class="foot">
      <span class="muted">@if ($projects->total())Affichage {{ $projects->firstItem() }} à {{ $projects->lastItem() }} sur {{ $projects->total() }}@else Aucun résultat @endif</span>
      {{ $projects->onEachSide(1)->links('back.partials.pagination') }}
    </div>
  </div>
</section>
@endsection
