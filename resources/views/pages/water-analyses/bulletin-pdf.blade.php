{{-- A5 : Bulletin d'analyse PDF signé (dompdf) — fragment sans <html>/<body>, styles inline.
     Inclut : en-tête, tableau des mesures, sparklines SVG 30 jours, QR code de vérification, prédiction IA (B2). --}}
<style>
  * { font-family: DejaVu Sans, sans-serif; }
  body { color: #0B2233; font-size: 11px; margin: 0; padding: 0; }
  h1 { font-size: 18px; color: #0E4A72; margin: 0 0 2px; }
  h2 { font-size: 13px; color: #0E4A72; margin: 16px 0 5px; border-bottom: 1px solid #DCE6EE; padding-bottom: 3px; }
  .muted { color: #5B7487; }
  .caption { font-size: 9px; color: #5B7487; text-transform: uppercase; letter-spacing: .04em; }
  table { width: 100%; border-collapse: collapse; margin: 6px 0; }
  th { text-align: left; font-size: 9px; color: #5B7487; text-transform: uppercase; padding: 5px 6px; border-bottom: 1px solid #DCE6EE; }
  td { padding: 6px; border-bottom: 1px solid #DCE6EE; vertical-align: middle; font-size: 11px; }
  td.label { color: #5B7487; width: 32%; }
  .badge-ok   { color: #0e6245; background: #d1fae5; border-radius: 4px; padding: 1px 6px; font-size: 10px; }
  .badge-crit { color: #991b1b; background: #fee2e2; border-radius: 4px; padding: 1px 6px; font-size: 10px; }
  .badge-anom { color: #7c3aed; background: #ede9fe; border-radius: 4px; padding: 1px 6px; font-size: 10px; }
  .sparkline-wrap { width: 120px; }
  .qr-block { float: right; text-align: center; margin-left: 12px; }
  .footer { margin-top: 20px; font-size: 9px; color: #5B7487; border-top: 1px solid #DCE6EE; padding-top: 8px; }
  .prediction-box { border-left: 3px solid #1CA7EC; background: #f0f9ff; padding: 8px 10px; margin: 8px 0; border-radius: 0 4px 4px 0; }
</style>

@php
    use App\Enums\WaterParameter;
    $complianceRate = $analysis->compliance_rate;
    $nonCompliant = $analysis->measurements->where('is_compliant', false)->count();
    $hasAnomalies = $analysis->measurements->where('is_anomaly', true)->isNotEmpty();
    $statusColor = $nonCompliant === 0 ? '#0e6245' : '#991b1b';
@endphp

{{-- En-tête --}}
<table style="margin: 0 0 12px;">
  <tr>
    <td style="border: none; padding: 0; vertical-align: top; width: 70%;">
      <h1>AquaSecure · Bulletin d'analyse</h1>
      <div class="muted">{{ $analysis->reference }} · édité le {{ $generatedAt->format('d/m/Y à H:i') }}</div>
      <div style="margin-top: 10px;">
        <span style="font-size: 22px; font-weight: bold; color: {{ $statusColor }};">
          {{ $complianceRate ?? '—' }}{{ $complianceRate !== null ? ' %' : '' }}
        </span>
        <span class="muted" style="font-size: 11px;"> de conformité</span>
      </div>
    </td>
    <td style="border: none; padding: 0; text-align: right; vertical-align: top;">
      <div class="qr-block">
        <img src="{{ $qrDataUri }}" width="80" height="80" alt="QR vérification" />
        <div class="caption" style="margin-top: 2px;">Vérifier l'authenticité</div>
      </div>
    </td>
  </tr>
</table>

{{-- Informations de l'analyse --}}
<h2>Informations</h2>
<table>
  <tr>
    <td class="label">Infrastructure</td>
    <td>{{ $analysis->infrastructure->code }} · {{ $analysis->infrastructure->name }}</td>
    <td class="label">Zone</td>
    <td>{{ $analysis->infrastructure->zone->name ?? '—' }}</td>
  </tr>
  <tr>
    <td class="label">Point de prélèvement</td>
    <td>{{ $analysis->sampling_point }}</td>
    <td class="label">Date du prélèvement</td>
    <td>{{ $analysis->sampled_at->format('d/m/Y à H:i') }}</td>
  </tr>
  <tr>
    <td class="label">Laboratoire</td>
    <td>{{ $analysis->laboratory ?? '—' }}</td>
    <td class="label">Technicien</td>
    <td>{{ $analysis->sampledBy?->name ?? '—' }}</td>
  </tr>
</table>

{{-- Résultats des mesures --}}
<h2>Résultats des mesures</h2>
<table>
  <thead>
    <tr>
      <th>Paramètre</th>
      <th>Valeur mesurée</th>
      <th>Seuil réglementaire</th>
      <th>Conformité</th>
      <th>Évolution 30 j</th>
    </tr>
  </thead>
  <tbody>
    @forelse ($analysis->measurements as $measurement)
    @php
      $param = $measurement->parameter instanceof WaterParameter
          ? $measurement->parameter
          : WaterParameter::from($measurement->parameter);
      $val = rtrim(rtrim(number_format((float) $measurement->value, 3, ',', ' '), '0'), ',');
      $sparkData = $history[$param->value] ?? [];
    @endphp
    <tr>
      <td><strong>{{ $param->label() }}</strong></td>
      <td style="font-family: monospace;">
        {{ $val }}{{ $param->unit() ? ' '.$param->unit() : '' }}
        @if ($measurement->is_anomaly ?? false)
          <span class="badge-anom">anomalie</span>
        @endif
      </td>
      <td class="muted" style="font-size: 10px;">{{ $param->thresholdHint() }}</td>
      <td>
        @if ($measurement->is_compliant)
          <span class="badge-ok">Conforme</span>
        @else
          <span class="badge-crit">Non conforme</span>
        @endif
      </td>
      <td class="sparkline-wrap">
        @if (count($sparkData) >= 2)
        @php
          $vals = array_column($sparkData, 'value');
          $min = min($vals); $max = max($vals);
          $range = max(0.0001, $max - $min);
          $w = 120; $h = 28; $pts = [];
          foreach ($vals as $i => $v) {
              $x = round($i / (count($vals) - 1) * $w, 1);
              $y = round($h - (($v - $min) / $range) * ($h - 4) - 2, 1);
              $pts[] = "{$x},{$y}";
          }
          $polyline = implode(' ', $pts);
          $lastCompliant = end($sparkData)['compliant'];
          $lineColor = $lastCompliant ? '#2A9D8F' : '#E63946';
        @endphp
        <svg width="{{ $w }}" height="{{ $h }}" style="overflow:visible;">
          <polyline points="{{ $polyline }}" fill="none" stroke="{{ $lineColor }}" stroke-width="1.5" stroke-linejoin="round" stroke-linecap="round"/>
          @foreach ($sparkData as $i => $pt)
          @php
            $px = round($i / max(1, count($sparkData) - 1) * $w, 1);
            $py = round($h - (($pt['value'] - $min) / $range) * ($h - 4) - 2, 1);
            $dotColor = $pt['compliant'] ? '#2A9D8F' : '#E63946';
          @endphp
          <circle cx="{{ $px }}" cy="{{ $py }}" r="2" fill="{{ $dotColor }}"/>
          @endforeach
        </svg>
        <div class="caption" style="margin-top: 1px;">{{ reset($sparkData)['date'] }} → {{ end($sparkData)['date'] }}</div>
        @else
        <span class="muted" style="font-size: 9px;">Historique insuffisant</span>
        @endif
      </td>
    </tr>
    @if (($measurement->is_anomaly ?? false) && $measurement->anomaly_explanation)
    <tr>
      <td colspan="5" class="muted" style="font-size: 10px; padding-top: 2px; padding-bottom: 8px;">
        ⚠ {{ $measurement->anomaly_explanation }}
      </td>
    </tr>
    @endif
    @empty
    <tr><td colspan="5" class="muted" style="text-align: center; padding: 12px;">Aucune mesure enregistrée.</td></tr>
    @endforelse
  </tbody>
</table>

{{-- Prédiction de dégradation (B2) --}}
@if ($degradationPrediction)
<h2>Analyse prédictive</h2>
<div class="prediction-box">
  <div class="caption" style="margin-bottom: 4px;">Prédiction de dégradation · {{ $analysis->infrastructure->type->label() }} {{ $analysis->infrastructure->code }}</div>
  {{ $degradationPrediction }}
</div>
@endif

{{-- Notes --}}
@if ($analysis->notes)
<h2>Notes</h2>
<p>{{ $analysis->notes }}</p>
@endif

<div class="footer">
  Bulletin d'analyse AquaSecure · {{ $analysis->reference }} · {{ $analysis->infrastructure->zone->name ?? '' }}
  · Vérifier l'authenticité de ce document : {{ $verifyUrl }}
  @if ($hasAnomalies) · ⚠ Des anomalies statistiques ont été détectées sur ce prélèvement.@endif
</div>
