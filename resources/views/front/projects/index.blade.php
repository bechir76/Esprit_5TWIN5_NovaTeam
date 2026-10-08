@extends('layouts.front.app')

@section('title', 'Projets')

@section('content')
<section class="pt-10 pb-16" data-page="projets">
  <div class="mx-auto max-w-[1200px] px-5">
    <div class="mb-7 flex flex-wrap items-end justify-between gap-4 [&_p]:max-w-[60ch]"><div class="flex flex-col gap-2"><span class="kicker">Rénovation et décontamination</span><h2>Projets à financer</h2><p class="text-muted">Chaque dinar collecté est affecté au projet choisi. Les dépenses sont publiées à chaque étape des travaux.</p></div>
      <form class="flex flex-wrap items-center gap-2" method="GET" action="{{ route('projects.index') }}">
        <select class="input w-auto" name="categorie" aria-label="Catégorie" onchange="this.form.submit()">
          <option value="">Toutes les catégories</option>
          @foreach (\App\Enums\ProjectCategory::cases() as $category)
          <option value="{{ $category->value }}" @selected(($filters['categorie'] ?? '') === $category->value)>{{ $category->label() }}</option>
          @endforeach
        </select>
        <select class="input w-auto" name="tri" aria-label="Trier" onchange="this.form.submit()">
          @foreach ($sorts as $value => $label)
          <option value="{{ $value }}" @selected(($filters['tri'] ?? 'urgents') === $value)>{{ $label }}</option>
          @endforeach
        </select>
        <noscript><button class="btn btn-ghost" type="submit">Appliquer</button></noscript>
      </form></div>
    <div class="grid gap-5 grid-fit-[260px]" id="allProjects">
      @forelse ($projects as $project)
      <x-project-card :project="$project" />
      @empty
      <p class="text-muted">Aucun projet ne correspond à ces critères pour le moment.</p>
      @endforelse
    </div>
    <div class="mt-4 flex justify-end">{{ $projects->onEachSide(1)->links('back.partials.pagination') }}</div>
  </div>
</section>
@endsection
