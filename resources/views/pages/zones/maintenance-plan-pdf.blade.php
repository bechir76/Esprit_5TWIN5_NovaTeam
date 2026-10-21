{{-- Plan de maintenance PDF d'une zone (dompdf) : fragment sans <html>/<body>, styles inline (pas de Vite).
     Document interne (personnel uniquement, ZonePolicy::planMaintenance). --}}
@php($plan = $zone->maintenance_plan)
<style>
  * { font-family: DejaVu Sans, sans-serif; }
  body { color: #0B2233; font-size: 12px; }
  h1 { font-size: 20px; color: #0E4A72; margin: 0 0 4px; }
  h2 { font-size: 14px; color: #0E4A72; margin: 18px 0 6px; }
  .muted { color: #5B7487; }
  .action { border-bottom: 1px solid #DCE6EE; padding: 8px 0; }
  .rank { display: inline-block; width: 20px; height: 20px; line-height: 20px; text-align: center; border-radius: 10px; background: #E3F4FD; color: #0E4A72; font-weight: bold; }
  .footer { margin-top: 24px; font-size: 10px; }
</style>

<h1>AquaSecure · Plan de maintenance</h1>
<div class="muted">Zone {{ $zone->name }} ({{ $zone->municipality }}, {{ $zone->governorate }}) · généré le {{ $zone->maintenance_plan_at?->format('d/m/Y à H:i') }}</div>

<h2>Synthèse</h2>
<p>{{ $plan['summary'] }}</p>

<h2>Actions prioritaires</h2>
@forelse ($plan['actions'] as $action)
<div class="action">
  <div><span class="rank">{{ $action['rank'] }}</span> <strong>{{ $action['name'] }}</strong> <span class="muted">· {{ $action['code'] }} · {{ $action['type'] }} · état {{ mb_strtolower($action['status']) }}@if ($action['risk_score'] !== null) · risque {{ $action['risk_score'] }}/100 ({{ mb_strtolower($action['risk_level']) }})@endif</span></div>
  <p><strong>Action :</strong> {{ $action['action'] }}</p>
  <p class="muted"><strong>Justification :</strong> {{ $action['justification'] }}</p>
</div>
@empty
<p class="muted">Aucun ouvrage dans cette zone.</p>
@endforelse

<div class="footer muted">Document interne AquaSecure · ouvrages sélectionnés et classés par le code (score de risque, retard d'inspection, incidents ouverts, état) ; texte {{ $zone->maintenance_plan_source === 'ia' ? 'rédigé par l\'IA' : 'généré par des règles' }}.</div>
