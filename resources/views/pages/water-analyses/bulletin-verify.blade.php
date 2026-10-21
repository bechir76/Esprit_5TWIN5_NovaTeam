@extends('layouts.layout')

@section('title', 'Vérification bulletin '.$analysis->reference)

@section('content')
<section class="flex flex-col gap-3 max-w-xl mx-auto">

  <x-page-header title="Vérification d'authenticité" crumb="Bulletin · {{ $analysis->reference }}" />

  <div class="card p-[18px] flex flex-col gap-5">

    {{-- Résultat de la vérification --}}
    <div class="flex flex-col items-center gap-3 py-4">
      <div class="size-16 rounded-full flex items-center justify-center bg-teal-soft" style="background: var(--teal-soft);">
        <svg width="32" height="32" viewBox="0 0 24 24" fill="none" stroke="var(--teal)" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
          <path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/><polyline points="22 4 12 14.01 9 11.01"/>
        </svg>
      </div>
      <div class="text-center">
        <div class="font-semibold text-[1.05rem]">Document authentique</div>
        <div class="text-muted text-[.88rem] mt-1">Ce bulletin a bien été émis par AquaSecure et n'a pas été modifié.</div>
      </div>
    </div>

    <hr class="border-line">

    {{-- Détails de l'analyse --}}
    <div class="grid grid-fit-[170px] gap-3 text-[.9rem]">
      <div><span class="caption block">Référence</span><span class="font-mono">{{ $analysis->reference }}</span></div>
      <div><span class="caption block">Infrastructure</span><span>{{ $analysis->infrastructure->code }} · {{ $analysis->infrastructure->name }}</span></div>
      <div><span class="caption block">Zone</span><span>{{ $analysis->infrastructure->zone->name ?? '—' }}</span></div>
      <div><span class="caption block">Date du prélèvement</span><span class="font-mono">{{ $analysis->sampled_at->format('d/m/Y à H:i') }}</span></div>
      <div><span class="caption block">Statut</span>
        <x-status-badge :value="$analysis->status->label()" :variant="$analysis->status->badge()" dot />
      </div>
      <div>
        <span class="caption block">Taux de conformité</span>
        <span class="font-mono">
          @php $rate = $analysis->compliance_rate; @endphp
          {{ $rate !== null ? $rate.' %' : '—' }}
        </span>
      </div>
    </div>

    {{-- Paramètres --}}
    @if ($analysis->measurements->isNotEmpty())
    <div class="overflow-x-auto">
      <table class="data-table">
        <thead>
          <tr>
            <th>Paramètre</th>
            <th>Valeur</th>
            <th>Seuil</th>
            <th>Conformité</th>
          </tr>
        </thead>
        <tbody>
          @foreach ($analysis->measurements as $m)
          @php
            $param = $m->parameter instanceof \App\Enums\WaterParameter
                ? $m->parameter : \App\Enums\WaterParameter::from($m->parameter);
            $val = rtrim(rtrim(number_format((float) $m->value, 3, ',', ' '), '0'), ',');
          @endphp
          <tr>
            <td><strong>{{ $param->label() }}</strong></td>
            <td class="font-mono">{{ $val }} <small class="text-muted">{{ $param->unit() }}</small></td>
            <td class="text-muted text-[.84rem]">{{ $param->thresholdHint() }}</td>
            <td>
              @if ($m->is_compliant)
                <x-status-badge value="Conforme" variant="b-ok" dot />
              @else
                <x-status-badge value="Non conforme" variant="b-crit" dot />
              @endif
            </td>
          </tr>
          @endforeach
        </tbody>
      </table>
    </div>
    @endif

    <p class="text-muted text-[.82rem] text-center">
      Vérification effectuée le {{ now()->format('d/m/Y à H:i') }} · AquaSecure
    </p>

  </div>

</section>
@endsection
