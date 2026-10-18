@extends('layouts.layout')

@section('title', 'Projets')

@section('content')
<section class="pt-10 pb-16" data-page="projets">
  <div class="mx-auto max-w-[1200px] px-5">
    <div class="mb-7 flex flex-wrap items-end justify-between gap-4 [&_p]:max-w-[60ch]"><div class="flex flex-col gap-2"><span class="kicker">Rénovation et décontamination</span><h2>Projets à financer</h2><p class="text-muted">Chaque dinar collecté est affecté au projet choisi. Financement « tout ou rien » : si l'objectif n'est pas atteint à la date limite, les dons sont remboursés. <a class="underline" href="{{ route('projects.transparency') }}">Voir tous les dons</a></p></div>
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

    @if ($recommendations->isNotEmpty())
    {{-- B4 : projets recommandés d'après les signalements du citoyen et le score de priorité --}}
    <div class="mb-8">
      <h3 class="mb-3">Recommandés pour vous</h3>
      <div class="grid gap-5 grid-fit-[260px]">
        @foreach ($recommendations as $recommendation)
        <div class="flex flex-col gap-2">
          <x-project-card :project="$recommendation['project']" />
          <p class="text-[.84rem] text-muted">{{ $recommendation['reason'] }}</p>
        </div>
        @endforeach
      </div>
    </div>
    @endif

    <div class="grid items-start gap-6 min-[981px]:grid-cols-[minmax(0,1fr)_340px]">
      <div>
        <div class="grid gap-5 grid-fit-[260px]" id="allProjects">
          @forelse ($projects as $project)
          <x-project-card :project="$project" />
          @empty
          <p class="text-muted">Aucun projet ne correspond à ces critères pour le moment.</p>
          @endforelse
        </div>
        <div class="mt-4 flex justify-end">{{ $projects->onEachSide(1)->links('partials.pagination') }}</div>
      </div>
      <aside class="min-[981px]:sticky min-[981px]:top-[90px]">
        {{-- B5 : assistant de questions sur les projets (app/Livewire/ProjectAssistant.php) --}}
        <livewire:project-assistant />
      </aside>
    </div>
  </div>
</section>
@endsection
