@extends('layouts.back.app')

@section('title', 'Nouveau projet')

@section('content')
<section data-page="projet-form" class="stack">
  <x-page-header title="Nouveau projet de rénovation" crumb="Console / Projets / Nouveau" />
  <form class="form-layout" method="POST" action="{{ route('admin.projects.store') }}" novalidate>
    @csrf
    <div class="card"><div class="fsec"><h2>Projet</h2>
      <x-form.input name="title" label="Intitulé *" placeholder="Réhabilitation du réservoir de La Soukra" />
      <div class="fgrid">
        <x-form.select name="infrastructure_id" label="Infrastructure concernée *" :options="['RES-ARI-004' => 'RES-ARI-004 · Réservoir de La Soukra', 'CND-TUN-021' => 'CND-TUN-021 · Conduite Bab Saadoun']" hint="Relation : un projet appartient à une infrastructure." />
        <x-form.select name="category" label="Catégorie *" :options="['Décontamination', 'Rénovation', 'Surveillance', 'Sécheresse']" />
      </div>
      <x-form.textarea name="description" label="Description" /></div>
      <div class="fsec"><h2>Budget et calendrier</h2><div class="fgrid">
        <x-form.input name="budget" label="Budget objectif (TND) *" class="mono" placeholder="220 000" />
        <x-form.input name="starts_at" label="Début de la collecte *" type="date" />
        <x-form.input name="ends_at" label="Fin de la collecte *" type="date" />
      </div></div>
    </div>
    <aside class="stack" style="gap:18px"><div class="card card-b stack"><h2>Publication</h2><x-form.select name="status" label="Statut" :options="['Brouillon', 'En collecte']" /><div class="row between"><span>Afficher sur le site public</span><label class="toggle"><input type="checkbox" name="is_public" value="1" @checked(old('is_public')) aria-label="Publier"><span></span></label></div></div>
    <div class="row"><a class="btn btn-ghost" href="{{ route('admin.projects.index') }}" style="flex:1;justify-content:center">Annuler</a><button class="btn btn-primary" type="submit" style="flex:1;justify-content:center">Enregistrer</button></div></aside>
  </form>
</section>
@endsection
