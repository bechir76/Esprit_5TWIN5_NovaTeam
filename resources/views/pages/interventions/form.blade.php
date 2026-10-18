@extends('layouts.layout')

{{-- Formulaire partagé par create et edit. Ouvert depuis la fiche d'un incident (?incident=ID) :
     l'incident est présélectionné et l'enregistrement revient sur la fiche. --}}
@php
    $editing = $intervention->exists;
    $title = $editing ? 'Modifier l\'intervention' : 'Planifier une intervention';
    $hours = $intervention->estimated_hours !== null ? (float) $intervention->estimated_hours : null;
@endphp

@section('title', $title)

@section('content')
<section data-page="intervention-form" class="flex flex-col gap-3">
  <x-page-header :title="$title" :crumb="'Console / Interventions / '.($editing ? $intervention->title : 'Nouvelle')" />
  @if ($errors->any())
  <div class="callout callout-crit">Le formulaire contient {{ $errors->count() }} erreur(s). Corrigez les champs signalés en rouge.</div>
  @endif
  <form class="grid items-start gap-[18px] min-[1101px]:grid-cols-[minmax(0,1fr)_320px]" method="POST" action="{{ $editing ? route('admin.interventions.update', $intervention) : route('admin.interventions.store') }}" novalidate>
    @csrf
    @if ($editing)
    @method('PUT')
    @endif
    @if ($fromIncident || old('retour') === 'incident')
    <input type="hidden" name="retour" value="incident">
    @endif
    <div class="card">
      <div class="form-section"><h2>Incident et intervention</h2>
        <x-form.select name="incident_id" label="Incident *" :value="$intervention->incident_id" :options="['' => 'Choisir un incident ouvert'] + $incidents" />
        <x-form.input name="title" label="Intitulé *" :value="$intervention->title" placeholder="Réparation de la conduite DN 300" maxlength="150" />
        <div class="grid grid-fit-[220px] gap-3.5">
          <x-form.select name="team" label="Équipe *" :value="$intervention->team" :options="['' => 'Choisir une équipe'] + $teams" />
          <x-form.select name="technician_id" label="Technicien" :value="$intervention->technician_id" :options="['' => 'Non assigné'] + $technicians" hint="Utilisateurs ayant le rôle technicien." />
        </div>
      </div>
      <div class="form-section"><h2>Planification</h2>
        <div class="grid grid-fit-[220px] gap-3.5">
          <x-form.input name="scheduled_at" label="Date et heure prévues *" type="datetime-local" :value="$intervention->scheduled_at?->format('Y-m-d\TH:i')" />
          <x-form.input name="estimated_hours" label="Durée estimée (heures) *" :value="$hours" inputmode="decimal" placeholder="3" hint="Entre 0,5 et 72 h." />
        </div>
      </div>
      <div class="form-section"><h2>Compte rendu</h2>
        <x-form.textarea name="report" label="Compte rendu" :value="$intervention->report" hint="Obligatoire pour une intervention terminée." rows="8" />
        @if ($editing)
        {{-- B3 : brouillon structuré à partir de quelques mots-clés ; l'agent relit et corrige avant d'enregistrer --}}
        <div class="flex flex-col gap-2 rounded-[9px] border border-line p-3.5" id="aiReport" data-url="{{ route('admin.interventions.draft-report', $intervention) }}">
          <label class="text-[.88rem] font-semibold" for="aiKeywords">✦ Rédiger le compte rendu avec l'IA</label>
          <input class="input" id="aiKeywords" type="text" maxlength="500" placeholder="Ex. : joint remplacé, vanne DN 150, pression rétablie, surveiller 48 h">
          <div class="flex flex-wrap items-center gap-2.5"><button class="btn btn-ghost btn-sm" type="button" id="aiDraft">Proposer un brouillon</button><span class="text-[.8rem] text-muted" id="aiDraftMsg">Le brouillon remplace le texte du compte rendu ; rien n'est enregistré sans votre validation.</span></div>
        </div>
        @endif
        <x-form.input name="completed_at" label="Terminée le" type="datetime-local" :value="$intervention->completed_at?->format('Y-m-d\TH:i')" max="{{ now()->format('Y-m-d\TH:i') }}" hint="Renseignée automatiquement à l'heure actuelle si laissée vide pour une intervention terminée." />
      </div>
    </div>
    <aside class="flex flex-col gap-[18px]">
      <div class="card p-[18px] flex flex-col gap-3"><h2>Statut</h2>
        <x-form.select name="status" label="Statut *" :value="$intervention->status?->value ?? \App\Enums\InterventionStatus::Planifiee->value" :options="\App\Enums\InterventionStatus::options()" />
        <span class="text-[.78rem] text-muted">Une intervention « En cours » fait passer l'incident signalé ou qualifié « En intervention ».</span>
      </div>
      <div class="flex flex-wrap items-center gap-2.5"><a class="btn btn-ghost flex-1 justify-center" href="{{ $fromIncident ? route('admin.incidents.show', $intervention->incident_id) : ($editing ? route('admin.interventions.show', $intervention) : route('admin.interventions.index')) }}">Annuler</a><button class="btn btn-primary flex-1 justify-center" type="submit">Enregistrer</button></div>
    </aside>
  </form>
</section>
@endsection

@push('scripts')
<script>
  (() => {
    const box = document.getElementById('aiReport');
    if (!box) return;
    const button = document.getElementById('aiDraft');
    const message = document.getElementById('aiDraftMsg');
    button.addEventListener('click', async () => {
      const keywords = document.getElementById('aiKeywords').value.trim();
      if (keywords.length < 5) { message.textContent = 'Saisissez au moins quelques mots-clés.'; return; }
      button.disabled = true; button.textContent = 'Rédaction…';
      try {
        const res = await fetch(box.dataset.url, {
          method: 'POST',
          headers: { 'Content-Type': 'application/json', 'Accept': 'application/json', 'X-CSRF-TOKEN': document.querySelector('input[name=_token]').value },
          body: JSON.stringify({ keywords }),
        });
        if (res.status === 429) throw new Error('Trop de demandes : réessayez dans une minute.');
        if (!res.ok) throw new Error("Le brouillon n'est pas disponible pour le moment.");
        document.querySelector('textarea[name=report]').value = (await res.json()).report;
        message.textContent = 'Brouillon inséré : relisez-le avant d\'enregistrer.';
      } catch (e) {
        message.textContent = e.message;
      } finally {
        button.disabled = false; button.textContent = 'Proposer un brouillon';
      }
    });
  })();
</script>
@endpush
