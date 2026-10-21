@extends('layouts.layout')

@section('title', 'Résumé exécutif — Qualité eau')

@section('content')
<section data-page="water-executive-summary" class="flex flex-col gap-3">

  <x-page-header title="Résumé exécutif" crumb="Console / Qualité eau / Résumé IA">
    <div class="flex flex-wrap items-center gap-2.5">
      <a class="btn btn-ghost" href="{{ route('admin.water-analyses.index') }}">Liste des analyses</a>
    </div>
  </x-page-header>

  {{-- Formulaire de sélection de période --}}
  <div class="card p-[18px]">
    <form class="flex flex-wrap items-end gap-3" method="GET" action="{{ route('admin.water-analyses.executive-summary') }}">
      <div class="flex flex-col gap-1">
        <label class="caption" for="from">Du</label>
        <input class="input" type="date" id="from" name="from"
               value="{{ $from->format('Y-m-d') }}" max="{{ now()->format('Y-m-d') }}">
      </div>
      <div class="flex flex-col gap-1">
        <label class="caption" for="to">Au</label>
        <input class="input" type="date" id="to" name="to"
               value="{{ $to->format('Y-m-d') }}" max="{{ now()->format('Y-m-d') }}">
      </div>
      <button class="btn btn-primary" type="submit">
        <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="3"/><path d="M12 2v2M12 20v2M4.93 4.93l1.41 1.41M17.66 17.66l1.41 1.41M2 12h2M20 12h2M4.93 19.07l1.41-1.41M17.66 6.34l1.41-1.41"/></svg>
        Générer le résumé IA
      </button>
      {{-- Raccourcis de période --}}
      <div class="flex gap-1.5 flex-wrap">
        @foreach (['Ce mois' => [now()->startOfMonth()->format('Y-m-d'), now()->format('Y-m-d')], '30 jours' => [now()->subDays(30)->format('Y-m-d'), now()->format('Y-m-d')], '3 mois' => [now()->subMonths(3)->format('Y-m-d'), now()->format('Y-m-d')]] as $label => [$f, $t])
        <a class="btn btn-ghost btn-sm" href="{{ route('admin.water-analyses.executive-summary', ['from' => $f, 'to' => $t]) }}">{{ $label }}</a>
        @endforeach
      </div>
    </form>
  </div>

  @if ($summary)
  <div class="grid items-start gap-[18px] min-[1101px]:grid-cols-[minmax(0,1fr)_300px]">

    {{-- Résumé principal --}}
    <div class="card p-[18px] flex flex-col gap-4">
      <div class="flex items-center gap-2.5">
        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="var(--water)" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 2L2 7l10 5 10-5-10-5zM2 17l10 5 10-5M2 12l10 5 10-5"/></svg>
        <h2 class="!mb-0">Synthèse</h2>
        <x-status-badge :value="$summary['source'] === 'ia' ? 'Généré par IA' : 'Règles'" :variant="$summary['source'] === 'ia' ? 'b-info' : 'b-mute'" />
      </div>

      <p class="text-[.95rem] leading-relaxed max-w-[70ch]">{{ $summary['summary'] }}</p>

      @if (!empty($summary['highlights']))
      <ul class="flex flex-col gap-2">
        @foreach ($summary['highlights'] as $point)
        <li class="flex gap-2.5 items-start text-[.9rem]">
          <span class="mt-1 size-1.5 shrink-0 rounded-full bg-water"></span>
          <span>{{ $point }}</span>
        </li>
        @endforeach
      </ul>
      @endif

      <p class="caption text-muted mt-1">
        Période analysée : {{ $from->format('d/m/Y') }} → {{ $to->format('d/m/Y') }}
        · Généré le {{ now()->format('d/m/Y à H:i') }}
      </p>
    </div>

    {{-- Actions --}}
    <aside class="flex flex-col gap-3">
      <div class="card p-[18px] flex flex-col gap-3">
        <h2>Actions</h2>
        <a class="btn btn-ghost justify-start gap-2" href="{{ route('admin.water-analyses.index') }}">
          <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/></svg>
          Liste complète des analyses
        </a>
        <a class="btn btn-ghost justify-start gap-2" href="{{ route('admin.measurements.index') }}">
          <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="m21.73 18-8-14a2 2 0 0 0-3.48 0l-8 14A2 2 0 0 0 4 21h16a2 2 0 0 0 1.73-3Z"/><line x1="12" y1="9" x2="12" y2="13"/><line x1="12" y1="17" x2="12.01" y2="17"/></svg>
          Mesures non conformes
        </a>
        <a class="btn btn-ghost justify-start gap-2" href="{{ route('admin.water-analyses.import') }}">
          <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="17 8 12 3 7 8"/><line x1="12" y1="3" x2="12" y2="15"/></svg>
          Importer des analyses CSV
        </a>
      </div>

      <div class="card p-[18px] text-[.86rem] text-muted">
        <p>Ce résumé est généré à partir des données réelles de la base. Il synthétise les analyses et mesures de la période sélectionnée.</p>
        <p class="mt-2">Il ne remplace pas les rapports officiels.</p>
      </div>
    </aside>

  </div>
  @else
  <div class="card p-10 text-center text-muted">
    <svg class="mx-auto mb-3 opacity-40" width="40" height="40" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg>
    <p>Sélectionnez une période et cliquez sur <strong>Générer le résumé IA</strong>.</p>
  </div>
  @endif

</section>
@endsection
