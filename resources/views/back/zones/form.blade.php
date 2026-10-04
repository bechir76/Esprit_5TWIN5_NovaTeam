@extends('layouts.back.app')

{{-- Formulaire partagé par create (zone non enregistrée) et edit --}}
@php
    $editing = $zone->exists;
    $title = $editing ? 'Modifier '.$zone->name : 'Nouvelle zone';
    $governorates = ['' => 'Choisir un gouvernorat'] + array_combine(array_keys(\App\Models\Zone::GOVERNORATES), array_keys(\App\Models\Zone::GOVERNORATES));
@endphp

@section('title', $title)

@section('content')
<section data-page="zone-form" class="stack">
  <x-page-header :title="$title" :crumb="'Console / Zones / '.($editing ? 'Modifier' : 'Nouvelle')" />
  @if ($errors->any())
  <div class="alert alert-crit">Le formulaire contient {{ $errors->count() }} erreur(s). Corrigez les champs signalés en rouge.</div>
  @endif
  <form class="form-layout" method="POST" action="{{ $editing ? route('admin.zones.update', $zone) : route('admin.zones.store') }}" novalidate>
    @csrf
    @if ($editing)
    @method('PUT')
    @endif
    <div class="card">
      <div class="fsec"><h2>Identification</h2>
        <div class="fgrid">
          <x-form.input name="name" label="Nom de la zone *" :value="$zone->name" placeholder="Ariana Nord" maxlength="100" />
          <x-form.input name="municipality" label="Commune *" :value="$zone->municipality" placeholder="Ariana" maxlength="100" />
          <x-form.select name="governorate" label="Gouvernorat *" :value="$zone->governorate" :options="$governorates" />
          <x-form.input name="population_served" label="Population desservie" :value="$zone->population_served" inputmode="numeric" placeholder="115000" hint="Nombre d'habitants, facultatif." />
        </div>
        <x-form.textarea name="description" label="Description" :value="$zone->description" />
      </div>
    </div>
    <aside class="stack" style="gap:18px">
      @if ($editing)
      <div class="card card-b stack"><h2>Infrastructures</h2><p class="muted">{{ $zone->infrastructures_count }} infrastructure(s) rattachée(s) à cette zone.</p></div>
      @endif
      <div class="row"><a class="btn btn-ghost" href="{{ $editing ? route('admin.zones.show', $zone) : route('admin.zones.index') }}" style="flex:1;justify-content:center">Annuler</a><button class="btn btn-primary" type="submit" style="flex:1;justify-content:center">{{ $editing ? 'Enregistrer' : 'Créer' }}</button></div>
    </aside>
  </form>
</section>
@endsection
