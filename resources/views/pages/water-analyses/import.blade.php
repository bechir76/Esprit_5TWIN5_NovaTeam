@extends('layouts.layout')

@section('title', 'Importer des analyses')

@section('content')
<section data-page="water-analyses-import" class="flex flex-col gap-3">
  <x-page-header title="Import CSV multi-paramètres" crumb="Console / Qualité eau / Import CSV">
    <div class="flex flex-wrap items-center gap-2.5">
      <a class="btn btn-ghost" href="{{ route('admin.water-analyses.index') }}">Liste des analyses</a>
    </div>
  </x-page-header>

  <div class="grid items-start gap-[18px] min-[1101px]:grid-cols-[minmax(0,1fr)_320px]">

    {{-- Formulaire d'import --}}
    <div class="flex flex-col gap-[18px]">
      <form class="card flex flex-col gap-4 p-[18px]"
            method="POST"
            action="{{ route('admin.water-analyses.import.store') }}"
            enctype="multipart/form-data">
        @csrf

        <x-form.input
          name="file"
          label="Fichier CSV"
          type="file"
          accept=".csv,text/csv,.txt"
          hint="2 Mo maximum, séparateur ; ou virgule. Première ligne = en-têtes."
          required />

        <x-form.input
          name="laboratory"
          label="Laboratoire (optionnel)"
          placeholder="Laboratoire X — remplace la colonne du CSV si absente"
          :value="old('laboratory')" />

        <div>
          <button class="btn btn-primary" type="submit">Importer le fichier</button>
        </div>
      </form>

      {{-- Avertissements non bloquants (lignes ignorées) --}}
      @if (session('import_warnings'))
      <div class="card p-[18px]">
        <h2 class="mb-3">Avertissements d'import</h2>
        <ul class="flex flex-col gap-1.5">
          @foreach (session('import_warnings') as $warning)
          <li class="flex gap-2 text-[.88rem]">
            <svg class="shrink-0 mt-0.5 text-warn" width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="m21.73 18-8-14a2 2 0 0 0-3.48 0l-8 14A2 2 0 0 0 4 21h16a2 2 0 0 0 1.73-3Z"/><line x1="12" y1="9" x2="12" y2="13"/><line x1="12" y1="17" x2="12.01" y2="17"/></svg>
            <span>{{ $warning }}</span>
          </li>
          @endforeach
        </ul>
      </div>
      @endif
    </div>

    {{-- Panneau d'aide --}}
    <aside class="flex flex-col gap-[18px]">
      <div class="card p-[18px] flex flex-col gap-3">
        <h2>Format du fichier</h2>

        <p class="text-[.88rem] text-muted">
          La première ligne doit contenir les en-têtes suivants. Les colonnes en gras sont obligatoires.
          Les colonnes de paramètres sont détectées automatiquement.
        </p>

        <div class="flex flex-wrap gap-1.5 font-mono text-[.78rem]">
          @foreach (['infrastructure_code', 'sampled_at', 'sampling_point'] as $col)
          <span class="rounded bg-surface-2 px-1.5 py-0.5 font-bold">{{ $col }}</span>
          @endforeach
          @foreach (['laboratory', 'notes'] as $col)
          <span class="rounded bg-surface-2 px-1.5 py-0.5">{{ $col }}</span>
          @endforeach
          @foreach (\App\Enums\WaterParameter::cases() as $param)
          <span class="rounded bg-info-soft px-1.5 py-0.5">{{ $param->value }}</span>
          @endforeach
        </div>

        <ul class="flex flex-col gap-1.5 text-[.86rem] text-muted list-disc pl-4">
          <li><strong>infrastructure_code</strong> : code de l'ouvrage (ex : RES-ARI-001)</li>
          <li><strong>sampled_at</strong> : date et heure (YYYY-MM-DD HH:MM ou DD/MM/YYYY)</li>
          <li><strong>sampling_point</strong> : point de prélèvement</li>
          <li>Paramètres : valeur numérique en virgule ou point. Valeur vide = paramètre absent.</li>
          <li>Valeurs négatives et impossibles physiquement sont rejetées.</li>
        </ul>

        <a class="btn btn-ghost justify-center" href="{{ route('admin.water-analyses.import.template') }}">
          Télécharger le modèle CSV
        </a>
      </div>

      <div class="card p-[18px] flex flex-col gap-2">
        <h2>Règles d'import</h2>
        <ul class="flex flex-col gap-1.5 text-[.86rem] text-muted list-disc pl-4">
          <li>Maximum {{ config('water_quality.import.max_rows', 500) }} lignes par fichier.</li>
          <li>Si le code infrastructure est introuvable, l'import est annulé.</li>
          <li>Une date invalide annule l'import.</li>
          <li>Les lignes entièrement vides sont ignorées silencieusement.</li>
          <li>Les anomalies non bloquantes sont signalées après l'import.</li>
        </ul>
      </div>
    </aside>

  </div>
</section>
@endsection
