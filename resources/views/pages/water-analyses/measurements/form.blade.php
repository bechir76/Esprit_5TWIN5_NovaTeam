@extends('layouts.layout')

{{-- Formulaire partagé par create et edit d'une mesure individuelle --}}
@php
    $editing = $measurement->exists;
    $title   = $editing ? 'Modifier la mesure' : 'Ajouter une mesure';
    $selectedParam = $editing
        ? ($measurement->parameter instanceof \App\Enums\WaterParameter
            ? $measurement->parameter
            : \App\Enums\WaterParameter::from($measurement->parameter))
        : null;
@endphp

@section('title', $title)

@section('content')
<section data-page="measurement-form" class="flex flex-col gap-3">
  <x-page-header
    :title="$title"
    :crumb="'Console / Qualité eau / '.$analysis->reference.' / '.($editing ? 'Modifier mesure' : 'Nouvelle mesure')" />

  @if ($errors->any())
  <div class="callout callout-crit">Le formulaire contient {{ $errors->count() }} erreur(s).</div>
  @endif

  <form class="grid items-start gap-[18px] min-[1101px]:grid-cols-[minmax(0,1fr)_320px]" method="POST"
    action="{{ $editing
        ? route('admin.measurements.update', $measurement)
        : route('admin.measurements.store', $analysis) }}"
    novalidate>
    @csrf
    @if ($editing) @method('PUT') @endif

    <div class="card">
      <div class="form-section">
        <h2>Paramètre mesuré</h2>
        <div class="grid grid-fit-[220px] gap-3.5">
          <x-form.select
            name="parameter"
            label="Paramètre *"
            :value="old('parameter', $selectedParam?->value)"
            :options="['' => 'Choisir un paramètre'] + $available"
            id="paramSelect" />
          <x-form.input
            name="value"
            label="Valeur mesurée *"
            :value="old('value', $measurement->exists ? rtrim(rtrim(number_format((float) $measurement->value, 4, '.', ''), '0'), '.') : null)"
            inputmode="decimal"
            placeholder="0,00"
            id="valueInput" />
        </div>
        <div id="thresholdHelp" class="text-[.78rem] text-muted mt-1.5 min-h-[1.4em]">
          @if ($selectedParam)
          Seuil&nbsp;: {{ $selectedParam->thresholdHint() }}
          @if ($selectedParam->unit()) &mdash; Unité&nbsp;: {{ $selectedParam->unit() }} @endif
          @else
          Sélectionnez un paramètre pour afficher son seuil réglementaire.
          @endif
        </div>
      </div>
    </div>

    <aside class="flex flex-col gap-[18px]">
      <div class="card p-[18px] flex flex-col gap-3"><h2>Analyse parente</h2>
        <div class="grid grid-fit-[170px] gap-2">
          <div><span class="caption block">Référence</span><span class="font-mono">{{ $analysis->reference }}</span></div>
          <div><span class="caption block">Infrastructure</span><span>{{ $analysis->infrastructure->name }}</span></div>
          <div><span class="caption block">Prélèvement</span><span class="font-mono">{{ $analysis->sampled_at->format('d/m/Y H:i') }}</span></div>
          <div><span class="caption block">Statut actuel</span>
            <x-status-badge :value="$analysis->status->label()" :variant="$analysis->status->badge()" dot />
          </div>
        </div>
      </div>
      <div class="flex flex-wrap items-center gap-2.5">
        <a class="btn btn-ghost flex-1 justify-center" href="{{ route('admin.water-analyses.show', $analysis) }}"
         >Annuler</a>
        <button class="btn btn-primary flex-1 justify-center" type="submit">
          {{ $editing ? 'Enregistrer' : 'Ajouter' }}
        </button>
      </div>
    </aside>
  </form>
</section>

@push('scripts')
<script>
(function () {
  var thresholds = @json(collect(\App\Enums\WaterParameter::cases())->mapWithKeys(fn ($p) => [
      $p->value => ['hint' => $p->thresholdHint(), 'unit' => $p->unit()]
  ])->all());

  var sel  = document.getElementById('paramSelect');
  var help = document.getElementById('thresholdHelp');
  if (!sel || !help) return;

  sel.addEventListener('change', function () {
    var t = thresholds[this.value];
    if (t) {
      help.textContent = 'Seuil\u00a0: ' + t.hint + (t.unit ? ' \u2014 Unit\u00e9\u00a0: ' + t.unit : '');
    } else {
      help.textContent = 'Sélectionnez un paramètre pour afficher son seuil réglementaire.';
    }
  });
})();
</script>
@endpush
@endsection
