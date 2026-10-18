@extends('layouts.layout')

@section('title', $analysis->reference)

@php
    $icon = fn (string $paths) => '<svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round">'.$paths.'</svg>';
    $edit  = $icon('<path d="M12 20h9M16.5 3.5a2.1 2.1 0 0 1 3 3L7 19l-4 1 1-4Z"/>');
    $trash = $icon('<path d="M3 6h18M8 6V4h8v2M19 6l-1 14H6L5 6"/>');
    $rate  = $analysis->compliance_rate;
    $existingParams = $analysis->measurements->map(
        fn ($m) => $m->parameter instanceof \App\Enums\WaterParameter ? $m->parameter->value : $m->parameter
    )->all();
    $hasAvailable = count($existingParams) < count(\App\Enums\WaterParameter::cases());
@endphp

@section('content')
<section data-page="water-analysis" class="flex flex-col gap-3">

  <x-page-header :title="$analysis->reference" :crumb="'Console / Qualité eau / '.$analysis->reference">
    <x-slot:badges>
      <x-status-badge :value="$analysis->status->label()" :variant="$analysis->status->badge()" dot />
    </x-slot:badges>
    <div class="flex flex-wrap items-center gap-2.5">
      <a class="btn btn-ghost" href="{{ route('admin.water-analyses.edit', $analysis) }}">Modifier</a>
      <button class="btn btn-danger" type="button"
        data-del="{{ $analysis->reference }}"
        data-action="{{ route('admin.water-analyses.destroy', $analysis) }}">Supprimer</button>
    </div>
  </x-page-header>

  {{-- KPIs --}}
  <div class="grid gap-[18px] grid-fit-[170px]">
    <x-kpi-card label="Mesures" :value="$analysis->measurements->count()" />
    <x-kpi-card label="Conformes"
      :value="$analysis->measurements->where('is_compliant', true)->count()"
      color="var(--color-teal)" />
    <x-kpi-card label="Non conformes"
      :value="$analysis->measurements->where('is_compliant', false)->count()"
      :color="$analysis->measurements->where('is_compliant', false)->count() > 0 ? 'var(--color-crit)' : 'var(--color-teal)'" />
    <x-kpi-card label="Taux de conformité"
      :value="$rate !== null ? $rate.' %' : '—'"
      :color="$rate === 100 ? 'var(--color-teal)' : ($rate !== null ? 'var(--color-crit)' : 'var(--color-water)')" />
  </div>

  <div class="grid items-start gap-[18px] min-[1101px]:grid-cols-[minmax(0,1fr)_340px]">
    <div class="flex flex-col gap-[18px]">

      {{-- Fiche --}}
      <div class="card p-[18px] flex flex-col gap-4">
        <div class="grid grid-fit-[170px] gap-4">
          <div><span class="caption block">Infrastructure</span>
            <a class="text-info-fg underline" href="{{ route('admin.infrastructures.show', $analysis->infrastructure) }}">
              {{ $analysis->infrastructure->name }}
            </a>
          </div>
          <div><span class="caption block">Zone</span><span>{{ $analysis->infrastructure->zone->name ?? '—' }}</span></div>
          <div><span class="caption block">Point de prélèvement</span><span>{{ $analysis->sampling_point }}</span></div>
          <div><span class="caption block">Date du prélèvement</span><span class="font-mono">{{ $analysis->sampled_at->format('d/m/Y H:i') }}</span></div>
          <div><span class="caption block">Laboratoire</span><span>{{ $analysis->laboratory ?? '—' }}</span></div>
          <div><span class="caption block">Technicien</span><span>{{ $analysis->sampledBy?->name ?? '—' }}</span></div>
          <div><span class="caption block">Créée le</span><span class="font-mono">{{ $analysis->created_at->format('d/m/Y H:i') }}</span></div>
        </div>
        @if ($analysis->notes)
        <div><span class="caption block">Notes</span><p class="mt-1.5 max-w-[70ch]">{{ $analysis->notes }}</p></div>
        @endif
      </div>

      {{-- Tableau des mesures --}}
      <div class="card">
        <div class="card-header">
          <h2>Mesures</h2>
          @if ($hasAvailable)
          <a class="btn btn-primary btn-sm" href="{{ route('admin.measurements.create', $analysis) }}">+ Ajouter une mesure</a>
          @endif
        </div>
        <div class="overflow-x-auto">
          <table class="data-table">
            <thead>
              <tr>
                <th>Paramètre</th>
                <th>Valeur mesurée</th>
                <th>Seuil réglementaire</th>
                <th>Conformité</th>
                <th></th>
              </tr>
            </thead>
            <tbody>
              @forelse ($analysis->measurements as $measurement)
              @php
                $param = $measurement->parameter instanceof \App\Enums\WaterParameter
                    ? $measurement->parameter
                    : \App\Enums\WaterParameter::from($measurement->parameter);
                $val = rtrim(rtrim(number_format((float) $measurement->value, 4, ',', ' '), '0'), ',');
              @endphp
              <tr>
                <td><strong>{{ $param->label() }}</strong></td>
                <td class="font-mono">
                  {{ $val }}@if ($param->unit()) <small class="text-muted"> {{ $param->unit() }}</small>@endif
                </td>
                <td class="text-muted text-[.84rem]">{{ $param->thresholdHint() }}</td>
                <td>
                  @if ($measurement->is_compliant)
                    <x-status-badge value="Conforme" variant="b-ok" dot />
                  @else
                    <x-status-badge value="Non conforme" variant="b-crit" dot />
                  @endif
                </td>
                <td>
                  <div class="flex justify-end gap-1.5">
                    <a class="btn-icon" href="{{ route('admin.measurements.edit', $measurement) }}" title="Modifier">{!! $edit !!}</a>
                    <button class="btn-icon btn-icon-danger" type="button"
                      data-del="{{ $param->label() }}"
                      data-action="{{ route('admin.measurements.destroy', $measurement) }}"
                      title="Supprimer">{!! $trash !!}</button>
                  </div>
                </td>
              </tr>
              @empty
              <tr>
                <td colspan="5" class="text-muted text-center p-7">
                  Aucune mesure enregistrée.
                  @if ($hasAvailable)
                  <a class="underline" href="{{ route('admin.measurements.create', $analysis) }}">Ajouter la première mesure →</a>
                  @endif
                </td>
              </tr>
              @endforelse
            </tbody>
          </table>
        </div>
      </div>

    </div>

    {{-- Colonne latérale --}}
    <aside class="flex flex-col gap-[18px]">

      @if ($rate !== null)
      <div class="card p-[18px] flex flex-col gap-3"><h2>Conformité globale</h2>
        <div>
          <div class="flex flex-wrap items-center gap-2.5 justify-between mb-1.5">
            <span class="text-muted text-[.84rem]">
              {{ $analysis->measurements->where('is_compliant', true)->count() }}
              / {{ $analysis->measurements->count() }} paramètres
            </span>
            <span class="font-mono font-bold">{{ $rate }}&thinsp;%</span>
          </div>
          <div class="h-[10px] rounded-[5px] bg-line overflow-hidden">
            <div @class(['h-full rounded-[5px] transition-[width] duration-400', 'bg-teal' => $rate === 100, 'bg-warn' => $rate < 100 && $rate >= 60, 'bg-crit' => $rate < 60]) style="width:{{ $rate }}%"></div>
          </div>
        </div>
      </div>
      @endif

      <div class="card p-[18px] flex flex-col gap-3"><h2>Infrastructure</h2>
        <div class="grid grid-fit-[170px] gap-2">
          <div><span class="caption block">Code</span><span class="font-mono">{{ $analysis->infrastructure->code }}</span></div>
          <div><span class="caption block">Type</span><span>{{ $analysis->infrastructure->type->label() }}</span></div>
          <div><span class="caption block">État</span>
            <x-status-badge :value="$analysis->infrastructure->status->label()" :variant="$analysis->infrastructure->status->badge()" dot />
          </div>
        </div>
        <a class="btn btn-ghost justify-center mt-1.5"
          href="{{ route('admin.infrastructures.show', $analysis->infrastructure) }}">Voir l'infrastructure</a>
      </div>

    </aside>
  </div>

</section>
@endsection
