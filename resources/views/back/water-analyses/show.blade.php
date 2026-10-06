@extends('layouts.back.app')

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
<section data-page="water-analysis" class="stack">

  <x-page-header :title="$analysis->reference" :crumb="'Console / Qualité eau / '.$analysis->reference">
    <x-slot:badges>
      <x-status-badge :value="$analysis->status->label()" :variant="$analysis->status->badge()" dot />
    </x-slot:badges>
    <div class="row">
      <a class="btn btn-ghost" href="{{ route('admin.water-analyses.edit', $analysis) }}">Modifier</a>
      <button class="btn btn-danger" type="button"
        data-del="{{ $analysis->reference }}"
        data-action="{{ route('admin.water-analyses.destroy', $analysis) }}">Supprimer</button>
    </div>
  </x-page-header>

  {{-- KPIs --}}
  <div class="grid kpis">
    <x-kpi-card label="Mesures" :value="$analysis->measurements->count()" />
    <x-kpi-card label="Conformes"
      :value="$analysis->measurements->where('is_compliant', true)->count()"
      color="var(--teal)" />
    <x-kpi-card label="Non conformes"
      :value="$analysis->measurements->where('is_compliant', false)->count()"
      :color="$analysis->measurements->where('is_compliant', false)->count() > 0 ? 'var(--crit)' : 'var(--teal)'" />
    <x-kpi-card label="Taux de conformité"
      :value="$rate !== null ? $rate.' %' : '—'"
      :color="$rate === 100 ? 'var(--teal)' : ($rate !== null ? 'var(--crit)' : 'var(--water)')" />
  </div>

  <div class="detail">
    <div class="stack" style="gap:18px">

      {{-- Fiche --}}
      <div class="card card-b stack" style="gap:16px">
        <div class="kv">
          <div><span class="label">Infrastructure</span>
            <a href="{{ route('admin.infrastructures.show', $analysis->infrastructure) }}" style="color:var(--info-fg)">
              {{ $analysis->infrastructure->name }}
            </a>
          </div>
          <div><span class="label">Zone</span><span>{{ $analysis->infrastructure->zone->name ?? '—' }}</span></div>
          <div><span class="label">Point de prélèvement</span><span>{{ $analysis->sampling_point }}</span></div>
          <div><span class="label">Date du prélèvement</span><span class="mono">{{ $analysis->sampled_at->format('d/m/Y H:i') }}</span></div>
          <div><span class="label">Laboratoire</span><span>{{ $analysis->laboratory ?? '—' }}</span></div>
          <div><span class="label">Technicien</span><span>{{ $analysis->sampledBy?->name ?? '—' }}</span></div>
          <div><span class="label">Créée le</span><span class="mono">{{ $analysis->created_at->format('d/m/Y H:i') }}</span></div>
        </div>
        @if ($analysis->notes)
        <div><span class="label">Notes</span><p style="margin-top:6px;max-width:70ch">{{ $analysis->notes }}</p></div>
        @endif
      </div>

      {{-- Tableau des mesures --}}
      <div class="card">
        <div class="card-h">
          <h2>Mesures</h2>
          @if ($hasAvailable)
          <a class="btn btn-primary btn-sm" href="{{ route('admin.measurements.create', $analysis) }}">+ Ajouter une mesure</a>
          @endif
        </div>
        <div class="table-wrap">
          <table>
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
                <td class="mono">
                  {{ $val }}@if ($param->unit()) <small class="muted"> {{ $param->unit() }}</small>@endif
                </td>
                <td class="muted" style="font-size:.84rem">{{ $param->thresholdHint() }}</td>
                <td>
                  @if ($measurement->is_compliant)
                    <x-status-badge value="Conforme" variant="b-ok" dot />
                  @else
                    <x-status-badge value="Non conforme" variant="b-crit" dot />
                  @endif
                </td>
                <td>
                  <div class="acts">
                    <a class="act" href="{{ route('admin.measurements.edit', $measurement) }}" title="Modifier">{!! $edit !!}</a>
                    <button class="act del" type="button"
                      data-del="{{ $param->label() }}"
                      data-action="{{ route('admin.measurements.destroy', $measurement) }}"
                      title="Supprimer">{!! $trash !!}</button>
                  </div>
                </td>
              </tr>
              @empty
              <tr>
                <td colspan="5" class="muted" style="text-align:center;padding:28px">
                  Aucune mesure enregistrée.
                  @if ($hasAvailable)
                  <a href="{{ route('admin.measurements.create', $analysis) }}">Ajouter la première mesure →</a>
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
    <aside class="stack" style="gap:18px">

      @if ($rate !== null)
      <div class="card card-b stack"><h2>Conformité globale</h2>
        <div>
          <div class="row between" style="margin-bottom:6px">
            <span class="muted" style="font-size:.84rem">
              {{ $analysis->measurements->where('is_compliant', true)->count() }}
              / {{ $analysis->measurements->count() }} paramètres
            </span>
            <span class="mono" style="font-weight:700">{{ $rate }}&thinsp;%</span>
          </div>
          <div style="height:10px;border-radius:5px;background:var(--line);overflow:hidden">
            <div style="height:100%;border-radius:5px;background:{{ $rate===100 ? 'var(--teal)' : ($rate>=60 ? 'var(--warn)' : 'var(--crit)') }};width:{{ $rate }}%;transition:width .4s"></div>
          </div>
        </div>
      </div>
      @endif

      <div class="card card-b stack"><h2>Infrastructure</h2>
        <div class="kv" style="gap:8px">
          <div><span class="label">Code</span><span class="mono">{{ $analysis->infrastructure->code }}</span></div>
          <div><span class="label">Type</span><span>{{ $analysis->infrastructure->type->label() }}</span></div>
          <div><span class="label">État</span>
            <x-status-badge :value="$analysis->infrastructure->status->label()" :variant="$analysis->infrastructure->status->badge()" dot />
          </div>
        </div>
        <a class="btn btn-ghost" style="justify-content:center;margin-top:6px"
          href="{{ route('admin.infrastructures.show', $analysis->infrastructure) }}">Voir l'infrastructure</a>
      </div>

    </aside>
  </div>

</section>
@endsection
