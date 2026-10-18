@extends('layouts.layout')

{{-- Formulaire partagé par create (zone non enregistrée) et edit --}}
@php
    $editing = $zone->exists;
    $title = $editing ? 'Modifier '.$zone->name : 'Nouvelle zone';
    $governorates = ['' => 'Choisir un gouvernorat'] + array_combine(array_keys(\App\Models\Zone::GOVERNORATES), array_keys(\App\Models\Zone::GOVERNORATES));
@endphp

@section('title', $title)

@section('content')
<section data-page="zone-form" class="flex flex-col gap-3">
  <x-page-header :title="$title" :crumb="'Console / Zones / '.($editing ? 'Modifier' : 'Nouvelle')" />
  @if ($errors->any())
  <div class="callout callout-crit">Le formulaire contient {{ $errors->count() }} erreur(s). Corrigez les champs signalés en rouge.</div>
  @endif
  <form class="grid items-start gap-[18px] min-[1101px]:grid-cols-[minmax(0,1fr)_320px]" method="POST" action="{{ $editing ? route('admin.zones.update', $zone) : route('admin.zones.store') }}" novalidate>
    @csrf
    @if ($editing)
    @method('PUT')
    @endif
    <div class="card">
      <div class="form-section"><h2>Identification</h2>
        <div class="grid grid-fit-[220px] gap-3.5">
          <x-form.input name="name" label="Nom de la zone *" :value="$zone->name" placeholder="Ariana Nord" maxlength="100" />
          <x-form.input name="municipality" label="Commune *" :value="$zone->municipality" placeholder="Ariana" maxlength="100" />
          <x-form.select name="governorate" label="Gouvernorat *" :value="$zone->governorate" :options="$governorates" />
          <x-form.input name="population_served" label="Population desservie" :value="$zone->population_served" inputmode="numeric" placeholder="115000" hint="Nombre d'habitants, facultatif." />
        </div>
        <x-form.textarea name="description" label="Description" :value="$zone->description" />
      </div>
    </div>
    <aside class="flex flex-col gap-[18px]">
      @if ($editing)
      <div class="card p-[18px] flex flex-col gap-3"><h2>Infrastructures</h2><p class="text-muted">{{ $zone->infrastructures_count }} infrastructure(s) rattachée(s) à cette zone.</p></div>
      @endif
      <div class="flex flex-wrap items-center gap-2.5"><a class="btn btn-ghost flex-1 justify-center" href="{{ $editing ? route('admin.zones.show', $zone) : route('admin.zones.index') }}">Annuler</a><button class="btn btn-primary flex-1 justify-center" type="submit">{{ $editing ? 'Enregistrer' : 'Créer' }}</button></div>
    </aside>
  </form>
</section>
@endsection
