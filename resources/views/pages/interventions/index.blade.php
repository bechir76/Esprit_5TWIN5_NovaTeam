@extends('layouts.layout')

@section('title', 'Interventions')

@section('content')
<section data-page="interventions" class="flex flex-col gap-3">
  <x-page-header title="Interventions planifiées" crumb="Console / Interventions">
    <div class="flex flex-wrap items-center gap-2.5">
      <a class="btn btn-ghost" href="{{ route('admin.interventions.planning') }}">Planning</a>
      <a class="btn btn-ghost" href="{{ route('admin.incidents.index') }}">Incidents</a>
      <a class="btn btn-primary" href="{{ route('admin.interventions.create') }}">+ Planifier</a>
    </div>
  </x-page-header>
  {{-- Recherche, filtres, tri et pagination sans rechargement : app/Livewire/InterventionTable.php --}}
  <livewire:intervention-table />
</section>
@endsection
