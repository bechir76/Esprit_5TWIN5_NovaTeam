@extends('layouts.back.app')

{{-- Formulaire partagé par create (infrastructure non enregistrée) et edit --}}
@php
    $editing = $infrastructure->exists;
    $title = $editing ? 'Modifier '.$infrastructure->name : 'Nouvelle infrastructure';
    $materials = ['' => 'Non renseigné'] + array_combine(\App\Models\Infrastructure::MATERIALS, \App\Models\Infrastructure::MATERIALS);
    if ($infrastructure->material && ! isset($materials[$infrastructure->material])) {
        $materials[$infrastructure->material] = $infrastructure->material;
    }
    $unit = old('capacity_unit', $infrastructure->capacity_unit?->value);
@endphp

@section('title', $title)

@section('content')
<section data-page="infra-form" class="stack">
  <x-page-header :title="$title" :crumb="'Console / Infrastructures / '.($editing ? $infrastructure->code : 'Nouvelle')" />
  @if ($errors->any())
  <div class="alert alert-crit">Le formulaire contient {{ $errors->count() }} erreur(s). Corrigez les champs signalés en rouge.</div>
  @endif
  <form class="form-layout" method="POST" action="{{ $editing ? route('admin.infrastructures.update', $infrastructure) : route('admin.infrastructures.store') }}" novalidate>
    @csrf
    @if ($editing)
    @method('PUT')
    @endif
    <div class="card">
      <div class="fsec"><h2>Identification</h2>
        <div class="fgrid">
          <x-form.input name="code" label="Code ouvrage *" :value="$infrastructure->code" placeholder="RES-ARI-012" class="mono" maxlength="11" hint="Format ABC-ABC-000 : type, gouvernorat, numéro." />
          <x-form.input name="name" label="Nom *" :value="$infrastructure->name" placeholder="Réservoir El Menzah 9" maxlength="120" />
          <x-form.select name="type" label="Type *" :value="$infrastructure->type?->value" :options="['' => 'Choisir un type'] + \App\Enums\InfrastructureType::options()" />
          <x-form.select name="material" label="Matériau" :value="$infrastructure->material" :options="$materials" />
        </div>
      </div>
      <div class="fsec"><h2>Caractéristiques techniques</h2>
        <div class="fgrid">
          <div @class(['field', 'is-invalid' => $errors->hasAny(['capacity', 'capacity_unit'])])><label for="capacity">Capacité *</label><div class="row" style="flex-wrap:nowrap"><input class="input" id="capacity" name="capacity" inputmode="decimal" value="{{ old('capacity', $infrastructure->capacity !== null ? (float) $infrastructure->capacity : null) }}" placeholder="6000"><select class="input" style="width:90px" name="capacity_unit" aria-label="Unité">@foreach (\App\Enums\CapacityUnit::options() as $value => $label)<option value="{{ $value }}" @selected($unit === $value)>{{ $label }}</option>@endforeach</select></div>@error('capacity')<span class="error">{{ $message }}</span>@enderror @error('capacity_unit')<span class="error">{{ $message }}</span>@enderror</div>
          <x-form.input name="commissioned_year" label="Année de mise en service *" :value="$infrastructure->commissioned_year" inputmode="numeric" placeholder="{{ now()->year }}" />
          <x-form.input name="nominal_pressure" label="Pression nominale (bar)" :value="$infrastructure->nominal_pressure !== null ? (float) $infrastructure->nominal_pressure : null" inputmode="decimal" placeholder="4,0" />
          <x-form.input name="last_inspection_at" label="Dernière inspection" type="date" :value="$infrastructure->last_inspection_at?->format('Y-m-d')" max="{{ now()->format('Y-m-d') }}" />
        </div>
        <x-form.textarea name="description" label="Description" :value="$infrastructure->description" />
      </div>
      <div class="fsec"><h2>Localisation</h2>
        <div class="fgrid">
          <x-form.select name="zone_id" label="Zone *" :value="$infrastructure->zone_id" :options="['' => 'Choisir une zone'] + $zones" />
          <x-form.input name="latitude" label="Latitude" class="mono" :value="$infrastructure->latitude" inputmode="decimal" placeholder="36.8430" />
          <x-form.input name="longitude" label="Longitude" class="mono" :value="$infrastructure->longitude" inputmode="decimal" placeholder="10.1715" />
        </div>
        <x-location-map pickable :latitude="old('latitude', $infrastructure->latitude)" :longitude="old('longitude', $infrastructure->longitude)" />
        <p class="hint">Cliquez sur la carte pour placer le repère, ou saisissez la latitude et la longitude.</p>
      </div>
    </div>
    <aside class="stack" style="gap:18px">
      <div class="card card-b stack"><h2>État</h2>
        <x-form.select name="status" label="État actuel *" :value="$infrastructure->status?->value ?? \App\Enums\InfrastructureStatus::Bon->value" :options="\App\Enums\InfrastructureStatus::options()" />
        <div class="row between"><span>Visible sur la carte publique</span><label class="toggle"><input type="hidden" name="is_public" value="0"><input type="checkbox" name="is_public" value="1" @checked(old('is_public', $infrastructure->is_public ?? true)) aria-label="Visible"><span></span></label></div>
      </div>
      <div class="card card-b stack"><h2>Documents</h2><div style="border:2px dashed var(--line);border-radius:9px;padding:18px;text-align:center" class="muted">Plans, rapports d'inspection<br><span class="hint">PDF, 10 Mo max</span></div></div>
      <div class="row"><a class="btn btn-ghost" href="{{ $editing ? route('admin.infrastructures.show', $infrastructure) : route('admin.infrastructures.index') }}" style="flex:1;justify-content:center">Annuler</a><button class="btn btn-primary" type="submit" style="flex:1;justify-content:center">Enregistrer</button></div>
    </aside>
  </form>
</section>
@endsection
