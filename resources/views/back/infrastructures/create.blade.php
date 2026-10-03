@extends('layouts.back.app')

@section('title', 'Nouvelle infrastructure')

@section('content')
<section data-page="infra-form" class="stack">
  <x-page-header title="Nouvelle infrastructure" crumb="Console / Infrastructures / Nouvelle" />
  @if ($errors->any())
  <div class="alert alert-crit">Le formulaire contient {{ $errors->count() }} erreur(s). Corrigez les champs signalés en rouge.</div>
  @endif
  <form class="form-layout" method="POST" action="{{ route('admin.infrastructures.store') }}" novalidate>
    @csrf
    <div class="card">
      <div class="fsec"><h2>Identification</h2>
        <div class="fgrid">
          <x-form.input name="code" label="Code ouvrage *" placeholder="RES-ARI-012" />
          <x-form.input name="name" label="Nom *" placeholder="Réservoir El Menzah 9" />
          <x-form.select name="type" label="Type *" :options="['Réservoir', 'Conduite', 'Station de pompage', 'Station de traitement', 'Forage']" />
          <x-form.select name="material" label="Matériau" :options="['Béton armé', 'Fonte ductile', 'PEHD', 'Acier']" />
        </div>
      </div>
      <div class="fsec"><h2>Caractéristiques techniques</h2>
        <div class="fgrid">
          <div @class(['field', 'is-invalid' => $errors->has('capacity')])><label for="capacity">Capacité *</label><div class="row" style="flex-wrap:nowrap"><input class="input" id="capacity" name="capacity" value="{{ old('capacity') }}" placeholder="6000"><select class="input" style="width:90px" name="capacity_unit" aria-label="Unité">@foreach (['m³', 'L/s', 'km'] as $unit)<option @selected(old('capacity_unit') === $unit)>{{ $unit }}</option>@endforeach</select></div>@error('capacity')<span class="error">{{ $message }}</span>@enderror</div>
          <x-form.input name="commissioned_year" label="Année de mise en service *" placeholder="2026" />
          <x-form.input name="nominal_pressure" label="Pression nominale (bar)" placeholder="4,0" />
          <x-form.input name="inspected_at" label="Dernière inspection" type="date" />
        </div>
        <x-form.textarea name="description" label="Description" />
      </div>
      <div class="fsec"><h2>Localisation</h2>
        <div class="fgrid">
          <x-form.select name="zone" label="Zone *" :options="['Ariana', 'Tunis', 'Ben Arous', 'La Manouba']" />
          <x-form.input name="latitude" label="Latitude" class="mono" placeholder="36.8430" />
          <x-form.input name="longitude" label="Longitude" class="mono" placeholder="10.1715" />
        </div>
        <div class="mapbox"><svg viewBox="0 0 600 200" width="100%" height="100%" preserveAspectRatio="xMidYMid slice"><rect width="600" height="200" fill="var(--surface-2)"/><path d="M0 80 L600 110 M0 160 L600 140 M200 0 L230 200 M430 0 L400 200" stroke="var(--line)" stroke-width="12"/><circle cx="315" cy="95" r="26" fill="var(--water)" opacity=".2"/><circle cx="315" cy="95" r="9" fill="var(--water)" stroke="#fff" stroke-width="3"/></svg></div>
        <p class="hint">Cliquez sur la carte ou déplacez le repère pour ajuster la position.</p>
      </div>
    </div>
    <aside class="stack" style="gap:18px">
      <div class="card card-b stack"><h2>État</h2>
        <x-form.select name="status" label="État actuel *" :options="['Bon', 'À surveiller', 'Critique']" />
        <div class="row between"><span>Surveillance IA activée</span><label class="toggle"><input type="checkbox" name="ai_monitoring" value="1" @checked(old('ai_monitoring', true)) aria-label="Surveillance IA"><span></span></label></div>
        <div class="row between"><span>Visible sur la carte publique</span><label class="toggle"><input type="checkbox" name="is_public" value="1" @checked(old('is_public', true)) aria-label="Visible"><span></span></label></div>
      </div>
      <div class="card card-b stack"><h2>Documents</h2><div style="border:2px dashed var(--line);border-radius:9px;padding:18px;text-align:center" class="muted">Plans, rapports d'inspection<br><span class="hint">PDF, 10 Mo max</span></div></div>
      <div class="row"><a class="btn btn-ghost" href="{{ route('admin.infrastructures.index') }}" style="flex:1;justify-content:center">Annuler</a><button class="btn btn-primary" type="submit" style="flex:1;justify-content:center">Enregistrer</button></div>
    </aside>
  </form>
</section>
@endsection
