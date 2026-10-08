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
<section data-page="analysis-form" class="flex flex-col gap-3">
  <x-page-header :title="$title" :crumb="'Console / Qualité eau / '.($editing ? $analysis->reference : 'Nouvelle')" />

  @if ($errors->any())
  <div class="callout callout-crit">
    Le formulaire contient {{ $errors->count() }} erreur(s). Corrigez les champs signalés en rouge.
    @if ($errors->has('measurements'))
    <br><strong>{{ $errors->first('measurements') }}</strong>
    @endif
  </div>
  @endif

  <form class="grid items-start gap-[18px] min-[1101px]:grid-cols-[minmax(0,1fr)_320px]" method="POST"
    action="{{ $editing ? route('admin.water-analyses.update', $analysis) : route('admin.water-analyses.store') }}"
    novalidate>
    @csrf
    @if ($editing) @method('PUT') @endif

    {{-- Colonne principale --}}
    <div class="flex flex-col gap-0">
      <div class="card">
        <div class="form-section"><h2>Identification du prélèvement</h2>
          <div class="grid grid-fit-[220px] gap-3.5">
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
        <div class="form-section">
          <h2>Mesures physico-chimiques et bactériologiques</h2>
          <p class="text-[.78rem] text-muted">Saisissez au moins une valeur. Les champs laissés vides ne créent pas de mesure.
            Les unités et la conformité sont calculées automatiquement.</p>
          @error('measurements')
            <div class="callout callout-crit px-3 py-2">{{ $message }}</div>
          @enderror
          <div class="overflow-x-auto">
            <table class="data-table min-w-0">
              <thead>
                <tr>
                  <th class="w-[36%]">Paramètre</th>
                  <th class="w-[22%]">Valeur</th>
                  <th class="w-[16%]">Unité</th>
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
                    <div @class(['flex flex-col gap-1.5 [&>label]:text-[.84rem] [&>label]:font-semibold', '[&_.input]:border-crit [&_.input]:bg-crit-soft' => $hasError, 'm-0'])>
                      <input
                        class="input min-w-[90px]"
                        type="text"
                        inputmode="decimal"
                        name="{{ $fieldName }}"
                        value="{{ $displayVal }}"
                        placeholder="—"
                        aria-label="{{ $param->label() }}"
                       >
                      @error("measurements.{$param->value}.value")
                        <span class="text-[.78rem] font-medium text-crit-fg">{{ $message }}</span>
                      @enderror
                    </div>
                  </td>
                  <td class="text-muted text-[.84rem]">
                    {{ $param->unit() ?: 'sans unité' }}
                  </td>
                  <td class="text-muted text-[.82rem]">{{ $param->thresholdHint() }}</td>
                </tr>
                @endforeach
              </tbody>
            </table>
          </div>
        </div>
      </div>
    </div>

    {{-- Colonne latérale --}}
    <aside class="flex flex-col gap-[18px]">
      <div class="card p-[18px] flex flex-col gap-3"><h2>Statut</h2>
        <x-form.select
          name="status"
          label="Statut *"
          :value="old('status', $analysis->status?->value ?? \App\Enums\AnalysisStatus::EnAttente->value)"
          :options="\App\Enums\AnalysisStatus::options()"
          hint="Recalculé automatiquement d'après les mesures saisies." />
      </div>

      @if ($editing)
      <div class="card p-[18px] flex flex-col gap-3"><h2>Référence</h2>
        <span class="font-mono text-[1.1rem] font-bold">{{ $analysis->reference }}</span>
        <span class="text-[.78rem] text-muted">Générée automatiquement, non modifiable.</span>
      </div>
      @endif

      <div class="flex flex-wrap items-center gap-2.5">
        <a class="btn btn-ghost flex-1 justify-center"
          href="{{ $editing ? route('admin.water-analyses.show', $analysis) : route('admin.water-analyses.index') }}"
         >Annuler</a>
        <button class="btn btn-primary flex-1 justify-center" type="submit">
          {{ $editing ? 'Enregistrer' : 'Créer l\'analyse' }}
        </button>
      </div>
    </aside>
  </form>
</section>
@endsection
