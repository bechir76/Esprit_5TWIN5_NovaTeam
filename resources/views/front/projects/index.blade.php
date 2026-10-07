@extends('layouts.front.app')

@section('title', 'Projets')

@section('content')
<section class="screen" data-page="projets">
  <div class="wrap">
    <div class="screen-head"><div class="stack" style="gap:8px"><span class="eyebrow">Rénovation et décontamination</span><h2>Projets à financer</h2><p class="muted">Chaque dinar collecté est affecté au projet choisi. Les dépenses sont publiées à chaque étape des travaux.</p></div>
      <form class="row" method="GET" action="{{ route('projects.index') }}" style="gap:8px">
        <select class="input" style="width:auto" name="categorie" aria-label="Catégorie" onchange="this.form.submit()">
          <option value="">Toutes les catégories</option>
          @foreach (\App\Enums\ProjectCategory::cases() as $category)
          <option value="{{ $category->value }}" @selected(($filters['categorie'] ?? '') === $category->value)>{{ $category->label() }}</option>
          @endforeach
        </select>
        <select class="input" style="width:auto" name="tri" aria-label="Trier" onchange="this.form.submit()">
          @foreach ($sorts as $value => $label)
          <option value="{{ $value }}" @selected(($filters['tri'] ?? 'urgents') === $value)>{{ $label }}</option>
          @endforeach
        </select>
        <noscript><button class="btn btn-ghost" type="submit">Appliquer</button></noscript>
      </form></div>
    <div class="grid g3" id="allProjects">
      @forelse ($projects as $project)
      <x-project-card :project="$project" />
      @empty
      <p class="muted">Aucun projet ne correspond à ces critères pour le moment.</p>
      @endforelse
    </div>
    {{ $projects->onEachSide(1)->links('back.partials.pagination') }}
  </div>
</section>
@endsection
