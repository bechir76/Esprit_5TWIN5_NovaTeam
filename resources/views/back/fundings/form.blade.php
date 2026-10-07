@extends('layouts.back.app')

{{-- Formulaire partagé par create et edit ; depuis la fiche d'un projet (?projet=ID), le projet est fixé et l'enregistrement y revient --}}
@php
    $editing = $funding->exists;
    $title = $editing ? 'Modifier '.$funding->reference : 'Nouveau financement';
@endphp

@section('title', $title)

@section('content')
<section data-page="financement-form" class="stack">
  <x-page-header :title="$title" :crumb="'Console / Financements / '.($editing ? $funding->reference : 'Nouveau')" />
  @if ($errors->any())
  <div class="alert alert-crit">Le formulaire contient {{ $errors->count() }} erreur(s). Corrigez les champs signalés en rouge.</div>
  @endif
  <form class="form-layout" method="POST" action="{{ $editing ? route('admin.fundings.update', $funding) : route('admin.fundings.store') }}" novalidate>
    @csrf
    @if ($editing)
    @method('PUT')
    @endif
    @if ($fromProject || old('retour') === 'projet')
    <input type="hidden" name="retour" value="projet">
    @endif
    <div class="card">
      <div class="fsec"><h2>Financement</h2>
        <x-form.select name="project_id" label="Projet *" :value="$funding->project_id" :options="['' => 'Choisir un projet'] + $projects" />
        <div class="fgrid">
          <x-form.select name="source_type" label="Source *" :value="$funding->source_type?->value" :options="['' => 'Choisir une source'] + \App\Enums\FundingSource::options()" />
          <x-form.input name="donor_name" label="Financeur *" :value="$funding->donor_name" placeholder="Municipalité de La Soukra" maxlength="150" />
          <x-form.input name="amount" label="Montant (TND) *" class="mono" :value="$funding->amount !== null ? (float) $funding->amount : null" inputmode="decimal" placeholder="15 000" />
          <x-form.input name="funded_at" label="Date du financement *" type="date" :value="$funding->funded_at?->format('Y-m-d')" :max="today()->format('Y-m-d')" hint="Entre le début de la collecte du projet et aujourd'hui." />
        </div>
        <x-form.textarea name="note" label="Note" :value="$funding->note" hint="Convention, délibération municipale, contrepartie… (facultatif)" />
      </div>
    </div>
    <aside class="stack" style="gap:18px">
      @if ($editing)
      <div class="card card-b stack"><h2>Suivi</h2>
        <div class="row between"><span class="muted">Référence</span><span class="mono">{{ $funding->reference }}</span></div>
        @if ($funding->user)
        <div class="row between"><span class="muted">Compte citoyen</span><span>{{ $funding->user->name }}</span></div>
        @endif
      </div>
      @endif
      <div class="card card-b stack"><h2>Statut du projet</h2>
        <span class="hint">Le projet « En collecte » passe automatiquement à « Financé » dès que le total atteint le budget objectif, et revient « En collecte » s'il repasse en dessous.</span>
      </div>
      <div class="row"><a class="btn btn-ghost" href="{{ $editing ? route('admin.fundings.show', $funding) : ($fromProject ? route('admin.projects.show', $funding->project_id) : route('admin.fundings.index')) }}" style="flex:1;justify-content:center">Annuler</a><button class="btn btn-primary" type="submit" style="flex:1;justify-content:center">Enregistrer</button></div>
    </aside>
  </form>
</section>
@endsection
