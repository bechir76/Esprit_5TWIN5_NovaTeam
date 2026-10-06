@extends('layouts.back.app')

{{-- Formulaire partagé par create et edit.
     Les mesures sont intégrées directement : un tableau dynamique avec une ligne par paramètre.
     Enregistrement en transaction dans WaterAnalysisController. --}}
@php
    $editing = $analysis->exists;
    $title   = $editing ? 'Modifier '.$analysis->reference : 'Nouvelle analyse';

    // Indexer les mesures existantes par paramètre pour pré-remplir le tableau
    $existingMeasurements = $editing
        ? $analysis->measurements->keyBy(fn ($m) => $m->parameter instanceof \App\Enums\WaterParameter
            ? $m->parameter->value : $m->parameter)
        : collect();
@endphp

@section('title', $title)

@section('content')
<section data-page="analysis-form" class="stack">
  <x-page-header :title="$title" :crumb="'Console / Qualité eau / '.($editing ? $analysis->reference : 'Nouvelle')" />

  @if ($errors->any())
  <div class="alert alert-crit">
    Le formulaire contient {{ $errors->count() }} erreur(s). Corrigez les champs signalés en rouge.
    @if ($errors->has('measurements'))
    <br><strong>{{ $errors->first('measurements') }}</strong>
    @endif
  </div>
  @endif

  <form class="form-layout" method="POST"
    action="{{ $editing ? route('admin.water-analyses.update', $analysis) : route('admin.water-analyses.store') }}"
    novalidate>
    @csrf
    @if ($editing) @method('PUT') @endif

    {{-- Colonne principale --}}
    <div class="stack" style="gap:0">
      <div class="card">
        <div class="fsec"><h2>Identification du prélèvement</h2>
          <div class="fgrid">
            <x-form.select
              name="infrastructure_id"
              label="Infrastructure *"
              :value="old('infrastructure_id', $analysis->infrastructure_id)"
              :options="['' => 'Choisir une infrastructure'] + $infrastructures->all()" />
            <x-form.input
              name="sampled_at"
              label="Date et heure du prélèvement *"
              type="datetime-local"
              :value="old('sampled_at', $analysis->sampled_at?->format('Y-m-d\TH:i'))"
              max="{{ now()->format('Y-m-d\TH:i') }}" />
            <x-form.input
              name="sampling_point"
              label="Point de prélèvement *"
              :value="old('sampling_point', $analysis->sampling_point)"
              placeholder="Sortie réservoir, Robinet école Ibn Khaldoun…"
              maxlength="120" />
            <x-form.input
              name="laboratory"
              label="Laboratoire *"
              :value="old('laboratory', $analysis->laboratory)"
              placeholder="Laboratoire central SONEDE"
              maxlength="150" />
            <x-form.select
              name="sampled_by"
              label="Technicien préleveur"
              :value="old('sampled_by', $analysis->sampled_by)"
              :options="['' => 'Non assigné'] + $technicians->all()" />
          </div>
          <x-form.textarea name="notes" label="Notes" :value="old('notes', $analysis->notes)" />
        </div>

        {{-- Tableau de mesures dynamique --}}
        <div class="fsec">
          <h2>Mesures physico-chimiques et bactériologiques</h2>
          <p class="hint">Saisissez au moins une valeur. Les champs laissés vides ne créent pas de mesure.
            Les unités et la conformité sont calculées automatiquement.</p>
          @error('measurements')
            <div class="alert alert-crit" style="padding:8px 12px">{{ $message }}</div>
          @enderror
          <div class="table-wrap">
            <table style="min-width:0">
              <thead>
                <tr>
                  <th style="width:36%">Paramètre</th>
                  <th style="width:22%">Valeur</th>
                  <th style="width:16%">Unité</th>
                  <th>Seuil réglementaire</th>
                </tr>
              </thead>
              <tbody>
                @foreach (\App\Enums\WaterParameter::cases() as $param)
                @php
                  $oldVal = old("measurements.{$param->value}.value");
                  $existingVal = $existingMeasurements->get($param->value);
                  $displayVal = $oldVal ?? ($existingVal ? rtrim(rtrim(number_format((float) $existingVal->value, 4, '.', ''), '0'), '.') : '');
                  $fieldName = "measurements[{$param->value}][value]";
                  $hasError = $errors->has("measurements.{$param->value}.value");
                @endphp
                <tr>
                  <td><strong>{{ $param->label() }}</strong></td>
                  <td>
                    <div @class(['field', 'is-invalid' => $hasError]) style="margin:0">
                      <input
                        class="input"
                        type="text"
                        inputmode="decimal"
                        name="{{ $fieldName }}"
                        value="{{ $displayVal }}"
                        placeholder="—"
                        aria-label="{{ $param->label() }}"
                        style="min-width:90px">
                      @error("measurements.{$param->value}.value")
                        <span class="error">{{ $message }}</span>
                      @enderror
                    </div>
                  </td>
                  <td class="muted" style="font-size:.84rem">
                    {{ $param->unit() ?: 'sans unité' }}
                  </td>
                  <td class="muted" style="font-size:.82rem">{{ $param->thresholdHint() }}</td>
                </tr>
                @endforeach
              </tbody>
            </table>
          </div>
        </div>
      </div>
    </div>

    {{-- Colonne latérale --}}
    <aside class="stack" style="gap:18px">
      <div class="card card-b stack"><h2>Statut</h2>
        <x-form.select
          name="status"
          label="Statut *"
          :value="old('status', $analysis->status?->value ?? \App\Enums\AnalysisStatus::EnAttente->value)"
          :options="\App\Enums\AnalysisStatus::options()"
          hint="Recalculé automatiquement d'après les mesures saisies." />
      </div>

      @if ($editing)
      <div class="card card-b stack"><h2>Référence</h2>
        <span class="mono" style="font-size:1.1rem;font-weight:700">{{ $analysis->reference }}</span>
        <span class="hint">Générée automatiquement, non modifiable.</span>
      </div>
      @endif

      <div class="row">
        <a class="btn btn-ghost"
          href="{{ $editing ? route('admin.water-analyses.show', $analysis) : route('admin.water-analyses.index') }}"
          style="flex:1;justify-content:center">Annuler</a>
        <button class="btn btn-primary" type="submit" style="flex:1;justify-content:center">
          {{ $editing ? 'Enregistrer' : 'Créer l\'analyse' }}
        </button>
      </div>
    </aside>
  </form>
</section>
@endsection
