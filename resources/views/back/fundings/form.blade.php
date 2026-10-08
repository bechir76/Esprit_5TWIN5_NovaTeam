@extends('layouts.back.app')

{{-- Formulaire partagé par create et edit ; depuis la fiche d'un projet (?projet=ID), le projet est fixé et l'enregistrement y revient --}}
@php
    $editing = $funding->exists;
    $title = $editing ? 'Modifier '.$funding->reference : 'Nouveau financement';
@endphp

@section('title', $title)

@section('content')
<section data-page="financement-form" class="flex flex-col gap-3">
  <x-page-header :title="$title" :crumb="'Console / Financements / '.($editing ? $funding->reference : 'Nouveau')" />
  @if ($errors->any())
  <div class="callout callout-crit">Le formulaire contient {{ $errors->count() }} erreur(s). Corrigez les champs signalés en rouge.</div>
  @endif
  <form class="grid items-start gap-[18px] min-[1101px]:grid-cols-[minmax(0,1fr)_320px]" method="POST" action="{{ $editing ? route('admin.fundings.update', $funding) : route('admin.fundings.store') }}" novalidate>
    @csrf
    @if ($editing)
    @method('PUT')
    @endif
    @if ($fromProject || old('retour') === 'projet')
    <input type="hidden" name="retour" value="projet">
    @endif
    <div class="card">
      <div class="form-section"><h2>Financement</h2>
        <x-form.select name="project_id" label="Projet *" :value="$funding->project_id" :options="['' => 'Choisir un projet'] + $projects" />
        <div class="grid grid-fit-[220px] gap-3.5">
          <x-form.select name="source_type" label="Source *" :value="$funding->source_type?->value" :options="['' => 'Choisir une source'] + \App\Enums\FundingSource::options()" />
          <x-form.input name="donor_name" label="Financeur *" :value="$funding->donor_name" placeholder="Municipalité de La Soukra" maxlength="150" />
          <x-form.input name="amount" label="Montant (TND) *" class="font-mono" :value="$funding->amount !== null ? (float) $funding->amount : null" inputmode="decimal" placeholder="15 000" />
          <x-form.input name="funded_at" label="Date du financement *" type="date" :value="$funding->funded_at?->format('Y-m-d')" :max="today()->format('Y-m-d')" hint="Entre le début de la collecte du projet et aujourd'hui." />
        </div>
        <x-form.textarea name="note" label="Note" :value="$funding->note" hint="Convention, délibération municipale, contrepartie… (facultatif)" />
      </div>
    </div>
    <aside class="flex flex-col gap-[18px]">
      @if ($editing)
      <div class="card p-[18px] flex flex-col gap-3"><h2>Suivi</h2>
        <div class="flex flex-wrap items-center gap-2.5 justify-between"><span class="text-muted">Référence</span><span class="font-mono">{{ $funding->reference }}</span></div>
        @if ($funding->user)
        <div class="flex flex-wrap items-center gap-2.5 justify-between"><span class="text-muted">Compte citoyen</span><span>{{ $funding->user->name }}</span></div>
        @endif
      </div>
      @endif
      <div class="card p-[18px] flex flex-col gap-3"><h2>Statut du projet</h2>
        <span class="text-[.78rem] text-muted">Le projet « En collecte » passe automatiquement à « Financé » dès que le total atteint le budget objectif, et revient « En collecte » s'il repasse en dessous.</span>
      </div>
      <div class="flex flex-wrap items-center gap-2.5"><a class="btn btn-ghost flex-1 justify-center" href="{{ $editing ? route('admin.fundings.show', $funding) : ($fromProject ? route('admin.projects.show', $funding->project_id) : route('admin.fundings.index')) }}">Annuler</a><button class="btn btn-primary flex-1 justify-center" type="submit">Enregistrer</button></div>
    </aside>
  </form>
</section>
@endsection
