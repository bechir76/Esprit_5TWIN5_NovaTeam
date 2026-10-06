@extends('layouts.front.app')

@section('title', 'Qualité de l\'eau')

@section('content')
<section data-page="water-quality">
  <div style="background:var(--hero);color:#fff;padding-block:44px 52px">
    <div class="wrap">
      <span class="eyebrow" style="color:#7FD8FF">Surveillance en temps réel</span>
      <h1 style="margin-top:12px;margin-bottom:14px">Qualité de l'eau potable</h1>
      <p style="color:#C7DDEB;max-width:56ch;font-size:1.05rem">
        Dernières analyses validées par zone. Les paramètres sont comparés aux seuils réglementaires en vigueur.
      </p>
    </div>
  </div>

  <div class="wrap" style="padding-block:40px 64px">

    {{-- Filtre par zone --}}
    <form method="GET" action="{{ route('water-quality') }}" style="margin-bottom:28px">
      <div class="row" style="gap:10px;flex-wrap:wrap">
        <label for="zoneFilter" style="font-weight:600;font-size:.9rem">Filtrer par zone&nbsp;:</label>
        <select class="input" id="zoneFilter" name="zone" style="width:auto;min-width:200px"
          onchange="this.form.submit()" aria-label="Choisir une zone">
          <option value="">Toutes les zones</option>
          @foreach ($zones as $zone)
          <option value="{{ $zone->id }}" @selected($selectedZone === $zone->id)>{{ $zone->name }}</option>
          @endforeach
        </select>
        @if ($selectedZone)
        <a href="{{ route('water-quality') }}" class="btn btn-ghost btn-sm">Réinitialiser</a>
        @endif
      </div>
    </form>

    @forelse ($lastByZone as $analysis)
    @php
      $zone    = $analysis->infrastructure->zone;
      $rate    = $analysis->compliance_rate;
      $byParam = $analysis->measurements->keyBy(
          fn ($m) => $m->parameter instanceof \App\Enums\WaterParameter
              ? $m->parameter->value
              : $m->parameter
      );
      $allOk = $rate === 100;
    @endphp

    <div class="card" style="margin-bottom:20px;overflow:hidden">
      {{-- En-tête de la zone --}}
      <div style="background:var(--surface-2);padding:16px 22px;display:flex;justify-content:space-between;align-items:flex-start;gap:12px;flex-wrap:wrap;border-bottom:1px solid var(--line)">
        <div>
          <div style="font-size:.75rem;text-transform:uppercase;letter-spacing:.08em;color:var(--muted);font-family:var(--f-mono)">
            {{ $zone->name ?? 'Zone inconnue' }}
          </div>
          <div style="font-weight:700;font-size:1.05rem;margin-top:3px">
            {{ $analysis->infrastructure->name }}
            <span style="font-weight:400;color:var(--muted);font-size:.88rem">· {{ $analysis->infrastructure->type->label() }}</span>
          </div>
          <div style="font-size:.82rem;color:var(--muted);margin-top:4px">
            Prélèvement du {{ $analysis->sampled_at->format('d/m/Y H:i') }}
            @if ($analysis->sampling_point) · {{ $analysis->sampling_point }} @endif
            @if ($analysis->laboratory) · {{ $analysis->laboratory }} @endif
          </div>
        </div>
        <div style="display:flex;gap:10px;align-items:center;flex-wrap:wrap">
          @if ($rate !== null)
          <div style="text-align:right">
            <div style="font-family:var(--f-mono);font-size:1.4rem;font-weight:800;line-height:1;color:{{ $allOk ? 'var(--teal)' : 'var(--crit)' }}">
              {{ $rate }}&thinsp;%
            </div>
            <div style="font-size:.76rem;color:var(--muted)">conformité</div>
          </div>
          @endif
          @if ($allOk)
            <span class="badge b-ok">✓ Conforme</span>
          @else
            <span class="badge b-crit">✕ Non conforme</span>
          @endif
        </div>
      </div>

      {{-- Grille des 4 paramètres principaux --}}
      <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(200px,1fr));gap:0;border-bottom:1px solid var(--line)">
        @foreach ($mainParams as $param)
        @php
          $m        = $byParam->get($param->value);
          $compliant = $m ? $m->is_compliant : null;
          $valStr   = $m ? rtrim(rtrim(number_format((float) $m->value, 4, ',', ''), '0'), ',') : null;
          $bgColor  = $compliant === true ? 'var(--ok-soft)' : ($compliant === false ? 'var(--crit-soft)' : 'var(--surface)');
          $fgColor  = $compliant === true ? 'var(--ok-fg)' : ($compliant === false ? 'var(--crit-fg)' : 'var(--muted)');
        @endphp
        <div style="padding:18px 20px;border-right:1px solid var(--line);background:{{ $bgColor }}">
          <div style="font-size:.78rem;font-weight:600;color:var(--muted);text-transform:uppercase;letter-spacing:.06em;margin-bottom:6px">
            {{ $param->label() }}
          </div>
          @if ($valStr !== null)
          <div style="font-family:var(--f-mono);font-size:1.3rem;font-weight:700;color:{{ $fgColor }};line-height:1">
            {{ $valStr }}@if ($param->unit()) <small style="font-size:.75rem;font-weight:400"> {{ $param->unit() }}</small>@endif
          </div>
          <div style="font-size:.76rem;color:var(--muted);margin-top:4px">{{ $param->thresholdHint() }}</div>
          <div style="margin-top:8px">
            @if ($compliant)
              <span class="badge b-ok">Conforme</span>
            @else
              <span class="badge b-crit">Non conforme</span>
            @endif
          </div>
          @else
          <div style="color:var(--muted);font-size:.9rem">Non mesuré</div>
          @endif
        </div>
        @endforeach
      </div>

      {{-- Barre de conformité globale + lien --}}
      @if ($rate !== null)
      <div style="padding:14px 22px;display:flex;align-items:center;gap:14px;flex-wrap:wrap">
        <div style="flex:1;min-width:120px">
          <div style="height:6px;border-radius:3px;background:var(--line);overflow:hidden">
            <div style="height:100%;border-radius:3px;background:{{ $allOk ? 'var(--teal)' : 'var(--crit)' }};width:{{ $rate }}%"></div>
          </div>
        </div>
        <span style="font-size:.82rem;color:var(--muted);white-space:nowrap">
          {{ $analysis->measurements->where('is_compliant', true)->count() }}
          / {{ $analysis->measurements->count() }} paramètres conformes
        </span>
      </div>
      @endif
    </div>

    @empty
    <div style="text-align:center;padding:48px;color:var(--muted)">
      <p style="font-size:1.1rem;margin-bottom:8px">Aucune analyse validée disponible.</p>
      <p>Les résultats apparaissent dès qu'une analyse est validée par le laboratoire.</p>
    </div>
    @endforelse

  </div>
</section>
@endsection
