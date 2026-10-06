@extends('layouts.back.app')

{{-- Formulaire partagé par create (incident non enregistré) et edit ; le statut se change depuis la fiche --}}
@php
    $editing = $incident->exists;
    $title = $editing ? 'Modifier '.$incident->reference : 'Nouvel incident';
@endphp

@section('title', $title)

@section('content')
<section data-page="incident-form" class="stack">
  <x-page-header :title="$title" :crumb="'Console / Incidents / '.($editing ? $incident->reference : 'Nouveau')" />
  @if ($errors->any())
  <div class="alert alert-crit">Le formulaire contient {{ $errors->count() }} erreur(s). Corrigez les champs signalés en rouge.</div>
  @endif
  <form class="form-layout" method="POST" action="{{ $editing ? route('admin.incidents.update', $incident) : route('admin.incidents.store') }}" enctype="multipart/form-data" novalidate>
    @csrf
    @if ($editing)
    @method('PUT')
    @endif
    <div class="card">
      <div class="fsec"><h2>Nature de l'incident</h2>
        <x-form.input name="title" label="Titre *" :value="$incident->title" placeholder="Fuite importante devant l'école Ibn Khaldoun" maxlength="120" />
        <div class="fgrid">
          <x-form.select name="type" label="Type d'incident *" :value="$incident->type?->value" :options="['' => 'Choisir un type'] + \App\Enums\IncidentType::options()" />
          <x-form.select name="severity" label="Gravité *" :value="$incident->severity?->value" :options="['' => 'Choisir une gravité'] + \App\Enums\IncidentSeverity::options()" />
        </div>
        <x-form.textarea name="description" label="Description *" :value="$incident->description" hint="30 caractères au moins : couleur, odeur, débit, depuis quand." />
      </div>
      <div class="fsec"><h2>Localisation</h2>
        <div class="fgrid">
          <x-form.input name="address" label="Adresse *" :value="$incident->address" placeholder="Avenue Habib Bourguiba, Ariana" />
          <x-form.select name="infrastructure_id" label="Infrastructure liée" :value="$incident->infrastructure_id" :options="['' => 'Aucune'] + $infrastructures" />
          <x-form.input name="latitude" label="Latitude" class="mono" :value="$incident->latitude" inputmode="decimal" placeholder="36.8660" />
          <x-form.input name="longitude" label="Longitude" class="mono" :value="$incident->longitude" inputmode="decimal" placeholder="10.1640" />
        </div>
        <x-location-map pickable :latitude="old('latitude', $incident->latitude)" :longitude="old('longitude', $incident->longitude)" color="var(--crit)" />
        <p class="hint">Cliquez sur la carte pour placer le repère, ou saisissez la latitude et la longitude.</p>
      </div>
    </div>
    <aside class="stack" style="gap:18px">
      <div class="card card-b stack"><h2>Photo</h2>
        @if ($incident->photo_path)
        <img src="{{ $incident->photoUrl() }}" alt="Photo actuelle" style="width:100%;max-height:180px;object-fit:cover;border-radius:8px">
        <label class="row" style="gap:8px;font-size:.86rem"><input type="checkbox" name="remove_photo" value="1" @checked(old('remove_photo'))> Retirer la photo actuelle</label>
        @endif
        <x-form.input name="photo" :label="$incident->photo_path ? 'Remplacer la photo' : 'Ajouter une photo'" type="file" accept="image/jpeg,image/png" hint="JPG ou PNG, 5 Mo maximum." />
      </div>
      @if ($editing)
      <div class="card card-b stack"><h2>Suivi</h2>
        <div class="row between"><span class="muted">Statut</span><x-status-badge :value="$incident->status->label()" :variant="$incident->status->badge()" /></div>
        <span class="hint">Le statut se change depuis la fiche de l'incident (bloc « Traitement »).</span>
      </div>
      @endif
      <div class="row"><a class="btn btn-ghost" href="{{ $editing ? route('admin.incidents.show', $incident) : route('admin.incidents.index') }}" style="flex:1;justify-content:center">Annuler</a><button class="btn btn-primary" type="submit" style="flex:1;justify-content:center">Enregistrer</button></div>
    </aside>
  </form>
</section>
@endsection
