@extends('layouts.back.app')

@section('title', 'Financements')

@php
    $icon = fn (string $paths) => '<svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round">'.$paths.'</svg>';
    $edit = $icon('<path d="M12 20h9M16.5 3.5a2.1 2.1 0 0 1 3 3L7 19l-4 1 1-4Z"/>');
    $trash = $icon('<path d="M3 6h18M8 6V4h8v2M19 6l-1 14H6L5 6"/>');
@endphp

@section('content')
<section data-page="financements" class="flex flex-col gap-3">
  <x-page-header title="Financements">
    <a class="btn btn-primary" href="{{ route('admin.fundings.create', array_filter(['projet' => $filters['projet'] ?? null])) }}">+ Enregistrer un financement</a>
  </x-page-header>
  <div class="grid gap-[18px] grid-fit-[170px]">
    <x-kpi-card label="Total collecté" :value="\App\Models\Project::money($totalCollected)" unit="TND" />
    <x-kpi-card label="Contributions" :value="number_format($contributionsCount, 0, ',', ' ')" />
    <x-kpi-card label="Part municipale" :value="$municipalShare.' %'" />
    <x-kpi-card label="Projets financés à 100 %" :value="$fullyFundedCount" />
  </div>
  <div class="grid gap-[18px] grid-cols-[repeat(auto-fit,minmax(min(100%,420px),1fr))]">
    <div class="card"><div class="card-header"><h2>Contributions récentes</h2></div>
      <form class="filter-bar" method="GET" action="{{ route('admin.fundings.index') }}">
        <input class="input min-w-[200px] flex-1" type="search" name="q" value="{{ $filters['q'] ?? '' }}" placeholder="Référence, financeur…" aria-label="Rechercher">
        <select class="input w-auto" name="projet" aria-label="Projet" onchange="this.form.submit()">
          <option value="">Tous les projets</option>
          @foreach ($projects as $id => $title)
          <option value="{{ $id }}" @selected((string) ($filters['projet'] ?? '') === (string) $id)>{{ $title }}</option>
          @endforeach
        </select>
        <select class="input w-auto" name="source" aria-label="Source" onchange="this.form.submit()">
          <option value="">Toutes les sources</option>
          @foreach (\App\Enums\FundingSource::cases() as $source)
          <option value="{{ $source->value }}" @selected(($filters['source'] ?? '') === $source->value)>{{ $source->label() }}</option>
          @endforeach
        </select>
        <input @class(['input', 'border-crit! bg-crit-soft!' => $errors->has('du')]) type="date" name="du" value="{{ $filters['du'] ?? request('du') }}" aria-label="Du" title="Du">
        <input @class(['input', 'border-crit! bg-crit-soft!' => $errors->has('au')]) type="date" name="au" value="{{ $filters['au'] ?? request('au') }}" aria-label="Au" title="Au">
        <button class="btn btn-ghost" type="submit">Filtrer</button>
        @if (array_filter($filters))
        <a class="btn btn-ghost" href="{{ route('admin.fundings.index') }}">Réinitialiser</a>
        @endif
      </form>
      <div class="overflow-x-auto"><table class="data-table min-w-[560px]"><thead><tr><th>Date</th><th>Source</th><th>Projet</th><th class="text-right">Montant</th><th></th></tr></thead><tbody>
      @forelse ($fundings as $funding)
      <tr>
        <td class="font-mono whitespace-nowrap">{{ $funding->funded_at->format('d/m/Y') }}<br><a href="{{ route('admin.fundings.show', $funding) }}" class="text-muted text-[.74rem]">{{ $funding->reference }}</a></td>
        <td>{{ $funding->donor_name }}<br><span class="text-muted text-[.78rem]">{{ $funding->source_type->label() }}@if ($funding->user) · compte citoyen @endif</span></td>
        <td><a class="underline" href="{{ route('admin.projects.show', $funding->project) }}">{{ $funding->project->title }}</a></td>
        <td class="font-mono text-right whitespace-nowrap">{{ \App\Models\Project::money($funding->amount) }} TND</td>
        <td><div class="flex justify-end gap-1.5">
          <a class="btn-icon" href="{{ route('admin.fundings.edit', $funding) }}" title="Modifier">{!! $edit !!}</a>
          <button class="btn-icon btn-icon-danger" type="button" data-del="{{ $funding->reference }}" data-action="{{ route('admin.fundings.destroy', $funding) }}" title="Supprimer">{!! $trash !!}</button>
        </div></td>
      </tr>
      @empty
      <tr><td colspan="5" class="text-muted text-center p-7">Aucun financement ne correspond à ces critères.</td></tr>
      @endforelse
      </tbody></table></div>
      <div class="card-footer">
        <span class="text-muted">@if ($fundings->total())Affichage {{ $fundings->firstItem() }} à {{ $fundings->lastItem() }} sur {{ $fundings->total() }}@else Aucun résultat @endif</span>
        {{ $fundings->onEachSide(1)->links('back.partials.pagination') }}
      </div>
    </div>
    <div class="card self-start"><div class="card-header"><h2>Progression par projet</h2></div><div class="p-[18px] flex flex-col gap-4" id="fundProg">
      @forelse ($progressProjects as $project)
      <x-project-progress :project="$project" />
      @empty
      <p class="text-muted">Aucun projet en collecte.</p>
      @endforelse
    </div></div>
  </div>
</section>
@endsection
