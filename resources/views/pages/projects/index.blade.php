@extends('layouts.layout')

@section('title', 'Projets de rénovation')

@section('content')
<section data-page="projets" class="flex flex-col gap-3">
  <x-page-header title="Projets de rénovation">
    <a class="btn btn-ghost" href="{{ route('admin.fundings.dashboard') }}">Tableau financier</a>
    <form method="POST" action="{{ route('admin.projects.scores') }}">
      @csrf
      <button class="btn btn-ghost" type="submit" title="Score de priorité calculé à partir des incidents, de l'état de l'ouvrage, de la population et du budget">Recalculer les priorités</button>
    </form>
    <a class="btn btn-primary" href="{{ route('admin.projects.create') }}">+ Nouveau projet</a>
  </x-page-header>

  {{-- Tableau, recherche, filtres, tri et pagination : composant Livewire (app/Livewire/ProjectTable.php) --}}
  <livewire:project-table />
</section>
@endsection
