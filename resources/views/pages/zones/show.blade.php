@extends('layouts.layout')

@section('title', $zone->name)

@php
    $icon = fn (string $paths) => '<svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round">'.$paths.'</svg>';
    $eye = $icon('<path d="M2 12s3.5-7 10-7 10 7 10 7-3.5 7-10 7S2 12 2 12Z"/><circle cx="12" cy="12" r="3"/>');
    $edit = $icon('<path d="M12 20h9M16.5 3.5a2.1 2.1 0 0 1 3 3L7 19l-4 1 1-4Z"/>');
@endphp

@section('content')
<section data-page="zone" class="flex flex-col gap-3">
  <x-page-header :title="$zone->name" :crumb="'Console / Zones / '.$zone->name">
    <div class="flex flex-wrap items-center gap-2.5"><a class="btn btn-ghost" href="{{ route('admin.zones.edit', $zone) }}">Modifier</a><button class="btn btn-danger" type="button" data-del="{{ $zone->name }}" data-action="{{ route('admin.zones.destroy', $zone) }}">Supprimer</button></div>
  </x-page-header>
  <div class="grid gap-[18px] grid-fit-[170px]">
    <x-kpi-card label="Infrastructures" :value="$infrastructures->count()" />
    @foreach (\App\Enums\InfrastructureStatus::cases() as $status)
    <x-kpi-card :label="$status->label()" :value="$statusCounts[$status->value] ?? 0" />
    @endforeach
  </div>
  <div class="card p-[18px] flex flex-col gap-4">
    <div class="grid grid-fit-[170px] gap-4">
      <div><span class="caption block">Commune</span><span>{{ $zone->municipality }}</span></div>
      <div><span class="caption block">Gouvernorat</span><span>{{ $zone->governorate }} <span class="font-mono text-muted">({{ $zone->governorateCode() }})</span></span></div>
      <div><span class="caption block">Population desservie</span><span class="font-mono">{{ $zone->population_served ? number_format($zone->population_served, 0, ',', ' ').' hab.' : '—' }}</span></div>
      <div><span class="caption block">Créée le</span><span class="font-mono">{{ $zone->created_at->format('d/m/Y') }}</span></div>
    </div>
    @if ($zone->description)
    <div><span class="caption block">Description</span><p class="mt-1.5 max-w-[70ch]">{{ $zone->description }}</p></div>
    @endif
  </div>
  {{-- A3 : Indice de Risque Sanitaire WQRI --}}
  @php
    $wqriLevel = $zone->wqri_level ? \App\Enums\WqriLevel::tryFrom($zone->wqri_level) : null;
    $wqriFactors = $zone->wqri_factors ?? [];
  @endphp
  <div class="card p-[18px] flex flex-col gap-4">
    <div class="flex flex-wrap items-center justify-between gap-3">
      <div class="flex items-center gap-2.5">
        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="var(--water)" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M12 2a7 7 0 0 1 7 7c0 5-7 13-7 13S5 14 5 9a7 7 0 0 1 7-7z"/><circle cx="12" cy="9" r="2.5"/></svg>
        <h2 class="!mb-0">Indice de Risque Sanitaire (WQRI)</h2>
      </div>
      <form method="POST" action="{{ route('admin.zones.maintenance-plan', $zone) }}">
        @csrf
        {{-- On réutilise l'endpoint de recalcul WQRI via Artisan ou on ajoute un bouton dédié ci-dessous --}}
      </form>
      @if ($zone->wqri_computed_at)
        <span class="text-[.78rem] text-muted">Calculé le {{ $zone->wqri_computed_at->format('d/m/Y à H:i') }}</span>
      @endif
    </div>

    @if ($zone->wqri_score !== null && $wqriLevel)
    <div class="grid grid-fit-[170px] gap-4">
      {{-- Score principal --}}
      <div class="flex flex-col gap-1">
        <span class="caption block">Score WQRI</span>
        <div class="flex items-end gap-2">
          <span class="font-mono text-[2rem] leading-none font-extrabold" style="color: var(--color-{{ match($wqriLevel) { \App\Enums\WqriLevel::Critique => 'crit', \App\Enums\WqriLevel::Eleve => 'warn', \App\Enums\WqriLevel::Moyen => 'info', \App\Enums\WqriLevel::Faible => 'teal' } }})">
            {{ $zone->wqri_score }}
          </span>
          <span class="text-muted text-[.85rem] mb-1">/ 100</span>
        </div>
        <div class="h-2 rounded-full bg-line overflow-hidden mt-1">
          <div class="h-full rounded-full" style="width:{{ $zone->wqri_score }}%; background: var(--color-{{ match($wqriLevel) { \App\Enums\WqriLevel::Critique => 'crit', \App\Enums\WqriLevel::Eleve => 'warn', \App\Enums\WqriLevel::Moyen => 'info', \App\Enums\WqriLevel::Faible => 'teal' } }})"></div>
        </div>
      </div>
      {{-- Niveau --}}
      <div class="flex flex-col gap-1">
        <span class="caption block">Niveau de risque</span>
        <x-status-badge :value="$wqriLevel->label()" :variant="$wqriLevel->badge()" dot />
      </div>
    </div>

    {{-- Facteurs détaillés --}}
    @if (!empty($wqriFactors))
    <div>
      <span class="caption block mb-2">Facteurs contributifs</span>
      <div class="flex flex-col gap-2">
        @foreach ($wqriFactors as $key => $factor)
        @php
          $pct = (int) round($factor['normalized'] * 100);
          $contribution = (int) round($factor['normalized'] * $factor['weight'] * 100);
        @endphp
        <div class="flex items-center gap-3">
          <div class="w-[140px] shrink-0 text-[.8rem] text-muted truncate capitalize">{{ str_replace('_', ' ', $key) }}</div>
          <div class="flex-1 flex items-center gap-2">
            <div class="flex-1 h-[6px] rounded-full bg-line overflow-hidden">
              <div class="h-full rounded-full @if($pct >= 70) bg-crit @elseif($pct >= 40) bg-warn @else bg-teal @endif" style="width:{{ $pct }}%"></div>
            </div>
            <span class="font-mono text-[.78rem] w-[38px] text-right text-muted">{{ $pct }}&thinsp;%</span>
          </div>
          <span class="text-[.75rem] text-muted w-[200px] hidden min-[900px]:block">{{ $factor['label'] }}</span>
        </div>
        @endforeach
      </div>
    </div>
    @endif

    {{-- Résumé IA --}}
    @if ($zone->wqri_summary)
    <div class="rounded-[6px] bg-surface-2 border border-line px-4 py-3">
      <div class="flex items-center gap-1.5 mb-1.5">
        <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="var(--water)" stroke-width="2" stroke-linecap="round"><path d="M12 2L2 7l10 5 10-5-10-5zM2 17l10 5 10-5M2 12l10 5 10-5"/></svg>
        <span class="text-[.75rem] font-semibold uppercase tracking-wider text-muted">Analyse IA</span>
      </div>
      <p class="text-[.88rem] leading-relaxed">{{ $zone->wqri_summary }}</p>
    </div>
    @endif

    @else
    <div class="rounded-[6px] bg-surface-2 border border-line px-4 py-4 text-[.88rem] text-muted flex items-center gap-2.5">
      <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg>
      WQRI non encore calculé pour cette zone.
      Lancez <code class="font-mono bg-line px-1.5 py-0.5 rounded text-[.8rem]">php artisan zones:score-wqri --zone={{ $zone->id }}</code>
      ou attendez le recalcul automatique quotidien.
    </div>
    @endif
  </div>

  {{-- B3 : plan de maintenance (ouvrages classés par le code, texte de l'IA ou des règles) --}}
  <livewire:zone-maintenance-plan :zone="$zone" />

  <div class="card">
    <div class="card-header"><h2>Infrastructures de la zone</h2><a class="btn btn-primary btn-sm" href="{{ route('admin.infrastructures.create', ['zone' => $zone->id]) }}">+ Ajouter</a></div>
    <div class="overflow-x-auto"><table class="data-table"><thead><tr><th>Code</th><th>Nom</th><th>Type</th><th>Capacité</th><th>État</th><th>Dernière inspection</th><th></th></tr></thead><tbody>
      @forelse ($infrastructures as $infrastructure)
      <tr>
        <td class="font-mono text-[.8rem]">{{ $infrastructure->code }}</td>
        <td><a href="{{ route('admin.infrastructures.show', $infrastructure) }}"><strong>{{ $infrastructure->name }}</strong></a></td>
        <td>{{ $infrastructure->type->label() }}</td>
        <td class="font-mono">{{ $infrastructure->formattedCapacity() }}</td>
        <td><x-status-badge :value="$infrastructure->status->label()" :variant="$infrastructure->status->badge()" dot /></td>
        <td class="font-mono">{{ $infrastructure->last_inspection_at?->format('d/m/Y') ?? '—' }}</td>
        <td><div class="flex justify-end gap-1.5">
          <a class="btn-icon" href="{{ route('admin.infrastructures.show', $infrastructure) }}" title="Voir">{!! $eye !!}</a>
          <a class="btn-icon" href="{{ route('admin.infrastructures.edit', $infrastructure) }}" title="Modifier">{!! $edit !!}</a>
        </div></td>
      </tr>
      @empty
      <tr><td colspan="7" class="text-muted text-center p-7">Aucune infrastructure dans cette zone : elle peut être supprimée.</td></tr>
      @endforelse
    </tbody></table></div>
  </div>
</section>
@endsection
