@extends('layouts.back.app')

@section('title', 'Financements')

@php
    $icon = fn (string $paths) => '<svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round">'.$paths.'</svg>';
    $edit = $icon('<path d="M12 20h9M16.5 3.5a2.1 2.1 0 0 1 3 3L7 19l-4 1 1-4Z"/>');
    $trash = $icon('<path d="M3 6h18M8 6V4h8v2M19 6l-1 14H6L5 6"/>');
@endphp

@section('content')
<section data-page="financements" class="stack">
  <x-page-header title="Financements">
    <a class="btn btn-primary" href="{{ route('admin.fundings.create', array_filter(['projet' => $filters['projet'] ?? null])) }}">+ Enregistrer un financement</a>
  </x-page-header>
  <div class="grid kpis">
    <x-kpi-card label="Total collecté" :value="\App\Models\Project::money($totalCollected)" unit="TND" />
    <x-kpi-card label="Contributions" :value="number_format($contributionsCount, 0, ',', ' ')" />
    <x-kpi-card label="Part municipale" :value="$municipalShare.' %'" />
    <x-kpi-card label="Projets financés à 100 %" :value="$fullyFundedCount" />
  </div>
  <div class="grid" style="grid-template-columns:repeat(auto-fit,minmax(min(100%,420px),1fr))">
    <div class="card"><div class="card-h"><h2>Contributions récentes</h2></div>
      <form class="filters" method="GET" action="{{ route('admin.fundings.index') }}">
        <input class="input grow" type="search" name="q" value="{{ $filters['q'] ?? '' }}" placeholder="Référence, financeur…" aria-label="Rechercher">
        <select class="input" style="width:auto" name="projet" aria-label="Projet" onchange="this.form.submit()">
          <option value="">Tous les projets</option>
          @foreach ($projects as $id => $title)
          <option value="{{ $id }}" @selected((string) ($filters['projet'] ?? '') === (string) $id)>{{ $title }}</option>
          @endforeach
        </select>
        <select class="input" style="width:auto" name="source" aria-label="Source" onchange="this.form.submit()">
          <option value="">Toutes les sources</option>
          @foreach (\App\Enums\FundingSource::cases() as $source)
          <option value="{{ $source->value }}" @selected(($filters['source'] ?? '') === $source->value)>{{ $source->label() }}</option>
          @endforeach
        </select>
        <input @class(['input', 'is-invalid' => $errors->has('du')]) type="date" name="du" value="{{ $filters['du'] ?? request('du') }}" aria-label="Du" title="Du">
        <input @class(['input', 'is-invalid' => $errors->has('au')]) type="date" name="au" value="{{ $filters['au'] ?? request('au') }}" aria-label="Au" title="Au">
        <button class="btn btn-ghost" type="submit">Filtrer</button>
        @if (array_filter($filters))
        <a class="btn btn-ghost" href="{{ route('admin.fundings.index') }}">Réinitialiser</a>
        @endif
      </form>
      <div class="table-wrap"><table style="min-width:560px"><thead><tr><th>Date</th><th>Source</th><th>Projet</th><th style="text-align:right">Montant</th><th></th></tr></thead><tbody>
      @forelse ($fundings as $funding)
      <tr>
        <td class="mono" style="white-space:nowrap">{{ $funding->funded_at->format('d/m/Y') }}<br><a href="{{ route('admin.fundings.show', $funding) }}" class="muted" style="font-size:.74rem;text-decoration:none">{{ $funding->reference }}</a></td>
        <td>{{ $funding->donor_name }}<br><span class="muted" style="font-size:.78rem">{{ $funding->source_type->label() }}@if ($funding->user) · compte citoyen @endif</span></td>
        <td><a href="{{ route('admin.projects.show', $funding->project) }}" style="color:inherit">{{ $funding->project->title }}</a></td>
        <td class="mono" style="text-align:right;white-space:nowrap">{{ \App\Models\Project::money($funding->amount) }} TND</td>
        <td><div class="acts">
          <a class="act" href="{{ route('admin.fundings.edit', $funding) }}" title="Modifier">{!! $edit !!}</a>
          <button class="act del" type="button" data-del="{{ $funding->reference }}" data-action="{{ route('admin.fundings.destroy', $funding) }}" title="Supprimer">{!! $trash !!}</button>
        </div></td>
      </tr>
      @empty
      <tr><td colspan="5" class="muted" style="text-align:center;padding:28px">Aucun financement ne correspond à ces critères.</td></tr>
      @endforelse
      </tbody></table></div>
      <div class="foot">
        <span class="muted">@if ($fundings->total())Affichage {{ $fundings->firstItem() }} à {{ $fundings->lastItem() }} sur {{ $fundings->total() }}@else Aucun résultat @endif</span>
        {{ $fundings->onEachSide(1)->links('back.partials.pagination') }}
      </div>
    </div>
    <div class="card" style="align-self:start"><div class="card-h"><h2>Progression par projet</h2></div><div class="card-b stack" style="gap:16px" id="fundProg">
      @forelse ($progressProjects as $project)
      <x-project-progress :project="$project" />
      @empty
      <p class="muted">Aucun projet en collecte.</p>
      @endforelse
    </div></div>
  </div>
</section>
@endsection
