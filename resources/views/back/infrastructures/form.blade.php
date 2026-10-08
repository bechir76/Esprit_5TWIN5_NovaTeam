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
<section data-page="infra-form" class="flex flex-col gap-3">
  <x-page-header :title="$title" :crumb="'Console / Infrastructures / '.($editing ? $infrastructure->code : 'Nouvelle')" />
  @if ($errors->any())
  <div class="callout callout-crit">Le formulaire contient {{ $errors->count() }} erreur(s). Corrigez les champs signalés en rouge.</div>
  @endif
  <form class="grid items-start gap-[18px] min-[1101px]:grid-cols-[minmax(0,1fr)_320px]" method="POST" action="{{ $editing ? route('admin.infrastructures.update', $infrastructure) : route('admin.infrastructures.store') }}" novalidate>
    @csrf
    @if ($editing)
    @method('PUT')
    @endif
    <div class="card">
      <div class="form-section"><h2>Identification</h2>
        <div class="grid grid-fit-[220px] gap-3.5">
          <x-form.input name="code" label="Code ouvrage *" :value="$infrastructure->code" placeholder="RES-ARI-012" class="font-mono" maxlength="11" hint="Format ABC-ABC-000 : type, gouvernorat, numéro." />
          <x-form.input name="name" label="Nom *" :value="$infrastructure->name" placeholder="Réservoir El Menzah 9" maxlength="120" />
          <x-form.select name="type" label="Type *" :value="$infrastructure->type?->value" :options="['' => 'Choisir un type'] + \App\Enums\InfrastructureType::options()" />
          <x-form.select name="material" label="Matériau" :value="$infrastructure->material" :options="$materials" />
        </div>
      </div>
      <div class="form-section"><h2>Caractéristiques techniques</h2>
        <div class="grid grid-fit-[220px] gap-3.5">
          <div @class(['flex flex-col gap-1.5 [&>label]:text-[.84rem] [&>label]:font-semibold', '[&_.input]:border-crit [&_.input]:bg-crit-soft' => $errors->hasAny(['capacity', 'capacity_unit'])])><label for="capacity">Capacité *</label><div class="flex items-center gap-2.5 flex-nowrap"><input class="input" id="capacity" name="capacity" inputmode="decimal" value="{{ old('capacity', $infrastructure->capacity !== null ? (float) $infrastructure->capacity : null) }}" placeholder="6000"><select class="input w-[90px]" name="capacity_unit" aria-label="Unité">@foreach (\App\Enums\CapacityUnit::options() as $value => $label)<option value="{{ $value }}" @selected($unit === $value)>{{ $label }}</option>@endforeach</select></div>@error('capacity')<span class="text-[.78rem] font-medium text-crit-fg">{{ $message }}</span>@enderror @error('capacity_unit')<span class="text-[.78rem] font-medium text-crit-fg">{{ $message }}</span>@enderror</div>
          <x-form.input name="commissioned_year" label="Année de mise en service *" :value="$infrastructure->commissioned_year" inputmode="numeric" placeholder="{{ now()->year }}" />
          <x-form.input name="nominal_pressure" label="Pression nominale (bar)" :value="$infrastructure->nominal_pressure !== null ? (float) $infrastructure->nominal_pressure : null" inputmode="decimal" placeholder="4,0" />
          <x-form.input name="last_inspection_at" label="Dernière inspection" type="date" :value="$infrastructure->last_inspection_at?->format('Y-m-d')" max="{{ now()->format('Y-m-d') }}" />
        </div>
        <x-form.textarea name="description" label="Description" :value="$infrastructure->description" />
      </div>
      <div class="form-section"><h2>Localisation</h2>
        <div class="grid grid-fit-[220px] gap-3.5">
          <x-form.select name="zone_id" label="Zone *" :value="$infrastructure->zone_id" :options="['' => 'Choisir une zone'] + $zones" />
          <x-form.input name="latitude" label="Latitude" class="font-mono" :value="$infrastructure->latitude" inputmode="decimal" placeholder="36.8430" />
          <x-form.input name="longitude" label="Longitude" class="font-mono" :value="$infrastructure->longitude" inputmode="decimal" placeholder="10.1715" />
        </div>
        <x-location-map pickable :latitude="old('latitude', $infrastructure->latitude)" :longitude="old('longitude', $infrastructure->longitude)" />
        <p class="text-[.78rem] text-muted">Cliquez sur la carte pour placer le repère, ou saisissez la latitude et la longitude.</p>
      </div>
    </div>
    <aside class="flex flex-col gap-[18px]">
      <div class="card p-[18px] flex flex-col gap-3"><h2>État</h2>
        <x-form.select name="status" label="État actuel *" :value="$infrastructure->status?->value ?? \App\Enums\InfrastructureStatus::Bon->value" :options="\App\Enums\InfrastructureStatus::options()" />
        <div class="flex flex-wrap items-center gap-2.5 justify-between"><span>Visible sur la carte publique</span><label class="relative inline-block h-[22px] w-10 flex-none"><input type="hidden" name="is_public" value="0"><input class="peer sr-only" type="checkbox" name="is_public" value="1" @checked(old('is_public', $infrastructure->is_public ?? true)) aria-label="Visible"><span class="switch-track"></span></label></div>
      </div>
      <div class="card p-[18px] flex flex-col gap-3"><h2>Documents</h2><div class="text-muted border-2 border-dashed border-line rounded-[9px] p-[18px] text-center">Plans, rapports d'inspection<br><span class="text-[.78rem] text-muted">PDF, 10 Mo max</span></div></div>
      <div class="flex flex-wrap items-center gap-2.5"><a class="btn btn-ghost flex-1 justify-center" href="{{ $editing ? route('admin.infrastructures.show', $infrastructure) : route('admin.infrastructures.index') }}">Annuler</a><button class="btn btn-primary flex-1 justify-center" type="submit">Enregistrer</button></div>
    </aside>
  </form>
</section>
@endsection
