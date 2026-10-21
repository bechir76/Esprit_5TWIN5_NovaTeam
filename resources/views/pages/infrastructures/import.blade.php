@extends('layouts.layout')

@section('title', 'Importer des infrastructures')

{{-- A9 : import CSV (InfrastructureImporter) : chaque ligne est validée avec les règles de StoreInfrastructureRequest,
     les lignes valides sont créées dans une transaction ; le rapport (session import_report) liste lignes importées et erreurs. --}}
@section('content')
<section data-page="infrastructures-import" class="flex flex-col gap-3">
  <x-page-header title="Importer des infrastructures" crumb="Console / Infrastructures / Import CSV">
    <div class="flex flex-wrap items-center gap-2.5"><a class="btn btn-ghost" href="{{ route('admin.infrastructures.index') }}">Liste</a></div>
  </x-page-header>

  <div class="grid items-start gap-[18px] min-[1101px]:grid-cols-[minmax(0,1fr)_320px]">
    <div class="flex flex-col gap-[18px]">
      <form class="card flex flex-col gap-4 p-[18px]" method="POST" action="{{ route('admin.infrastructures.import.store') }}" enctype="multipart/form-data">
        @csrf
        <x-form.input name="file" label="Fichier CSV" type="file" accept=".csv,text/csv" hint="2 Mo maximum, séparateur ; ou virgule, une infrastructure par ligne." required />
        <label class="flex items-center gap-2.5 text-[.9rem]"><input class="size-4 accent-water" type="checkbox" name="strict" value="1" @checked(old('strict'))> Tout ou rien : n'importer aucune ligne si une seule est en erreur</label>
        <div><button class="btn btn-primary" type="submit">Importer</button></div>
      </form>

      @if ($report)
      <div class="card">
        <div class="card-header"><h2>Rapport d'import</h2>
          <span class="flex flex-wrap gap-2">
            <x-status-badge :value="count($report['imported']).' importée(s)'" variant="b-ok" />
            <x-status-badge :value="count($report['errors']).' en erreur'" :variant="$report['errors'] ? 'b-crit' : 'b-mute'" />
            @if ($report['rolled_back'])<x-status-badge value="Import annulé" variant="b-warn" />@endif
          </span>
        </div>
        @if ($report['rolled_back'])
        <p class="callout callout-info m-[18px] mb-0">Mode « tout ou rien » : la transaction a été annulée, aucune infrastructure n'a été créée. Corrigez les lignes ci-dessous puis relancez l'import.</p>
        @endif
        <div class="overflow-x-auto"><table class="data-table"><thead><tr><th>Ligne</th><th>Résultat</th><th>Détail</th></tr></thead><tbody>
          @foreach (collect($report['imported'])->map(fn ($r) => $r + ['ok' => true])->merge(collect($report['errors'])->map(fn ($r) => $r + ['ok' => false]))->sortBy('line') as $row)
          <tr>
            <td class="font-mono">{{ $row['line'] }}</td>
            <td>@if ($row['ok'])<x-status-badge value="Importée" variant="b-ok" />@else<x-status-badge value="Erreur" variant="b-crit" />@endif</td>
            <td class="text-[.86rem]">
              @if ($row['ok'])
              <span class="font-mono">{{ $row['code'] }}</span> · {{ $row['name'] }}
              @else
              <ul class="list-disc pl-4">@foreach ($row['messages'] as $message)<li>{{ $message }}</li>@endforeach</ul>
              @endif
            </td>
          </tr>
          @endforeach
        </tbody></table></div>
      </div>
      @endif
    </div>

    <aside class="card flex flex-col gap-3 p-[18px]">
      <h2>Format du fichier</h2>
      <p class="text-[.88rem] text-muted">Première ligne : les en-têtes ci-dessous (dans n'importe quel ordre). Les colonnes en gras sont obligatoires.</p>
      <ul class="flex flex-wrap gap-1.5 font-mono text-[.78rem]">
        @foreach ($columns as $column)
        <li @class(['rounded bg-surface-2 px-1.5 py-0.5', 'font-bold' => in_array($column, $required, true)])>{{ $column }}</li>
        @endforeach
      </ul>
      <p class="text-[.88rem] text-muted">Type, état et unité : valeur ou libellé (« Réservoir », « À surveiller », « m³ »). Zone : nom ou identifiant. Date : 15/03/2026. Décimales avec virgule acceptées. Public : oui / non.</p>
      <a class="btn btn-ghost justify-center" href="{{ route('admin.infrastructures.import.template') }}">Télécharger le fichier modèle</a>
    </aside>
  </div>
</section>
@endsection
