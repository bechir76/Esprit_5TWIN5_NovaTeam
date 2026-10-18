@extends('layouts.layout')

{{-- Formulaire partagé par create (projet non enregistré) et edit ; le slug est généré depuis l'intitulé --}}
@php
    $editing = $project->exists;
    $title = $editing ? 'Modifier le projet' : 'Nouveau projet de rénovation';
@endphp

@section('title', $editing ? 'Modifier '.$project->title : 'Nouveau projet')

@section('content')
<section data-page="projet-form" class="flex flex-col gap-3">
  <x-page-header :title="$title" :crumb="'Console / Projets / '.($editing ? $project->title : 'Nouveau')" />
  @if ($errors->any())
  <div class="callout callout-crit">Le formulaire contient {{ $errors->count() }} erreur(s). Corrigez les champs signalés en rouge.</div>
  @endif
  <form class="grid items-start gap-[18px] min-[1101px]:grid-cols-[minmax(0,1fr)_320px]" method="POST" action="{{ $editing ? route('admin.projects.update', $project) : route('admin.projects.store') }}" enctype="multipart/form-data" novalidate>
    @csrf
    @if ($editing)
    @method('PUT')
    @endif
    <div class="card"><div class="form-section"><h2>Projet</h2>
      <x-form.input name="title" label="Intitulé *" :value="$project->title" placeholder="Réhabilitation du réservoir de La Soukra" maxlength="150" />
      <div class="grid grid-fit-[220px] gap-3.5">
        <x-form.select name="infrastructure_id" label="Infrastructure concernée" :value="$project->infrastructure_id" :options="['' => 'Aucune (multi-sites)'] + $infrastructures" hint="Relation : un projet appartient à une infrastructure." />
        <x-form.select name="category" label="Catégorie *" :value="$project->category?->value" :options="['' => 'Choisir une catégorie'] + \App\Enums\ProjectCategory::options()" />
      </div>
      <x-form.textarea name="description" label="Description *" :value="$project->description" rows="6" hint="20 caractères au moins : ouvrage, travaux prévus, bénéfices attendus." />
      {{-- B2 : proposition de description par l'IA (repli : texte gabarit) ; insérée dans le champ, jamais enregistrée automatiquement --}}
      <div class="flex flex-wrap items-center gap-2.5">
        <button class="btn btn-ghost btn-sm" type="button" id="aiDescribe" data-url="{{ route('admin.projects.describe') }}">✦ Générer avec l'IA</button>
        <span class="text-[.8rem] text-muted" id="aiDescribeStatus">À partir de l'intitulé, de la catégorie, du budget et de l'infrastructure. Relisez avant d'enregistrer.</span>
      </div></div>
      <div class="form-section"><h2>Budget et calendrier</h2><div class="grid grid-fit-[220px] gap-3.5">
        <x-form.input name="target_budget" label="Budget objectif (TND) *" class="font-mono" :value="$project->target_budget !== null ? (float) $project->target_budget : null" inputmode="decimal" placeholder="220 000" />
        <x-form.input name="start_date" label="Début de la collecte *" type="date" :value="$project->start_date?->format('Y-m-d')" />
        <x-form.input name="end_date" label="Fin de la collecte *" type="date" :value="$project->end_date?->format('Y-m-d')" />
      </div></div>
    </div>
    <aside class="flex flex-col gap-[18px]"><div class="card p-[18px] flex flex-col gap-3"><h2>Publication</h2><x-form.select name="status" label="Statut" :value="$project->status?->value" :options="$statuses" hint="Seuls les statuts atteignables sont proposés. « En collecte » passe automatiquement à « Financé » quand l'objectif est atteint ; « Échoué » rembourse les dons." /><div class="flex flex-wrap items-center gap-2.5 justify-between"><span>Afficher sur le site public</span><label class="relative inline-block h-[22px] w-10 flex-none"><input class="peer sr-only" type="checkbox" name="is_public" value="1" @checked(old('is_public', $project->is_public)) aria-label="Publier"><span class="switch-track"></span></label></div></div>
    <div class="card p-[18px] flex flex-col gap-3"><h2>Image</h2>
      @if ($project->image_path)
      <img class="w-full max-h-[180px] object-cover rounded-lg" src="{{ $project->imageUrl() }}" alt="Image actuelle">
      <label class="flex flex-wrap items-center gap-2 text-[.86rem]"><input type="checkbox" name="remove_image" value="1" @checked(old('remove_image'))> Retirer l'image actuelle</label>
      @endif
      <x-form.input name="image" :label="$project->image_path ? 'Remplacer l\'image' : 'Ajouter une image'" type="file" accept="image/jpeg,image/png" hint="JPG ou PNG, 2 Mo maximum." />
    </div>
    <div class="flex flex-wrap items-center gap-2.5"><a class="btn btn-ghost flex-1 justify-center" href="{{ $editing ? route('admin.projects.show', $project) : route('admin.projects.index') }}">Annuler</a><button class="btn btn-primary flex-1 justify-center" type="submit">Enregistrer</button></div></aside>
  </form>
</section>
@endsection

@push('scripts')
<script>
// « Générer avec l'IA » : la description proposée remplace le champ, l'administrateur la relit avant d'enregistrer
document.getElementById('aiDescribe')?.addEventListener('click', async (event) => {
  const button = event.currentTarget, status = document.getElementById('aiDescribeStatus');
  const field = (name) => document.querySelector(`[name="${name}"]`)?.value || null;
  button.disabled = true;
  status.textContent = 'Génération en cours…';
  try {
    const response = await fetch(button.dataset.url, {
      method: 'POST',
      headers: {'Content-Type': 'application/json', 'Accept': 'application/json', 'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content},
      body: JSON.stringify({title: field('title'), category: field('category'), target_budget: field('target_budget')?.replace(/[\s ]/g, '').replace(',', '.'), infrastructure_id: field('infrastructure_id')}),
    });
    const data = await response.json();
    if (!response.ok) {
      status.textContent = data.errors ? Object.values(data.errors).flat().join(' ') : (data.message || 'Génération impossible.');
      return;
    }
    document.getElementById('description').value = data.description;
    status.textContent = 'Description proposée : relisez-la et modifiez-la avant d\'enregistrer.';
  } catch {
    status.textContent = 'Génération impossible pour le moment.';
  } finally {
    button.disabled = false;
  }
});
</script>
@endpush
