@extends('layouts.back.app')

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
<section data-page="measurement-form" class="stack">
  <x-page-header
    :title="$title"
    :crumb="'Console / Qualité eau / '.$analysis->reference.' / '.($editing ? 'Modifier mesure' : 'Nouvelle mesure')" />

  @if ($errors->any())
  <div class="alert alert-crit">Le formulaire contient {{ $errors->count() }} erreur(s).</div>
  @endif

  <form class="form-layout" method="POST"
    action="{{ $editing
        ? route('admin.measurements.update', $measurement)
        : route('admin.measurements.store', $analysis) }}"
    novalidate>
    @csrf
    @if ($editing) @method('PUT') @endif

    <div class="card">
      <div class="fsec">
        <h2>Paramètre mesuré</h2>
        <div class="fgrid">
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
        <div id="thresholdHelp" class="hint" style="margin-top:6px;min-height:1.4em">
          @if ($selectedParam)
          Seuil&nbsp;: {{ $selectedParam->thresholdHint() }}
          @if ($selectedParam->unit()) &mdash; Unité&nbsp;: {{ $selectedParam->unit() }} @endif
          @else
          Sélectionnez un paramètre pour afficher son seuil réglementaire.
          @endif
        </div>
      </div>
    </div>

    <aside class="stack" style="gap:18px">
      <div class="card card-b stack"><h2>Analyse parente</h2>
        <div class="kv" style="gap:8px">
          <div><span class="label">Référence</span><span class="mono">{{ $analysis->reference }}</span></div>
          <div><span class="label">Infrastructure</span><span>{{ $analysis->infrastructure->name }}</span></div>
          <div><span class="label">Prélèvement</span><span class="mono">{{ $analysis->sampled_at->format('d/m/Y H:i') }}</span></div>
          <div><span class="label">Statut actuel</span>
            <x-status-badge :value="$analysis->status->label()" :variant="$analysis->status->badge()" dot />
          </div>
        </div>
      </div>
      <div class="row">
        <a class="btn btn-ghost" href="{{ route('admin.water-analyses.show', $analysis) }}"
          style="flex:1;justify-content:center">Annuler</a>
        <button class="btn btn-primary" type="submit" style="flex:1;justify-content:center">
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
