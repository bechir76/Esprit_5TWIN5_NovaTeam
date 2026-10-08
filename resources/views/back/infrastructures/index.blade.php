@extends('layouts.back.app')

@section('title', 'Infrastructures')

@section('content')
<section data-page="infras" class="flex flex-col gap-3">
  <x-page-header title="Infrastructures">
    <a class="btn btn-ghost" href="{{ route('admin.zones.index') }}">Zones</a>
    <a class="btn btn-primary" href="{{ route('admin.infrastructures.create') }}">+ Ajouter</a>
  </x-page-header>

  {{-- Tableau, recherche, filtres et pagination : composant Livewire (app/Livewire/InfrastructureTable.php) --}}
  <livewire:infrastructure-table />
</section>
@endsection
