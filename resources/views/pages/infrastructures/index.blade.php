@extends('layouts.layout')

@section('title', 'Infrastructures')

@include('partials.leaflet-assets')

@section('content')
<section data-page="infras" class="flex flex-col gap-3">
  <x-page-header title="Infrastructures">
    <a class="btn btn-ghost" href="{{ route('admin.zones.index') }}">Zones</a>
    <a class="btn btn-ghost" href="{{ route('admin.infrastructures.stats') }}">Statistiques</a>
    @can('import', \App\Models\Infrastructure::class)<a class="btn btn-ghost" href="{{ route('admin.infrastructures.import') }}">Importer CSV</a>@endcan
    @can('create', \App\Models\Infrastructure::class)<a class="btn btn-primary" href="{{ route('admin.infrastructures.create') }}">+ Ajouter</a>@endcan
  </x-page-header>

  {{-- Recherche en langage naturel, tableau, filtres et pagination : composant Livewire (app/Livewire/InfrastructureTable.php) --}}
  <livewire:infrastructure-table />

  {{-- Carte Leaflet avec ses propres filtres (app/Livewire/InfrastructureMap.php), chargée après le tableau (lazy) --}}
  <h2 class="mt-3">Carte des ouvrages</h2>
  <livewire:infrastructure-map lazy />
</section>
@endsection
