@extends('layouts.layout')

@section('title', 'Incidents')

@section('content')
<section data-page="incidents" class="flex flex-col gap-3">
  <x-page-header title="Incidents">
    <div class="flex flex-wrap items-center gap-2.5">
      <a class="btn btn-ghost" href="{{ route('admin.incidents.map') }}">Carte</a>
      <a class="btn btn-ghost" href="{{ route('admin.incidents.stats') }}">Statistiques</a>
      <a class="btn btn-ghost" href="{{ route('admin.interventions.index') }}">Interventions</a>
      <a class="btn btn-primary" href="{{ route('admin.incidents.create') }}">+ Créer un incident</a>
    </div>
  </x-page-header>
  {{-- Recherche, filtres, tri et pagination sans rechargement : app/Livewire/IncidentTable.php --}}
  <livewire:incident-table />
</section>
@endsection
