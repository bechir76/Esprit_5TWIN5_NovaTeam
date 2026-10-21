{{-- Fiche PDF d'un ouvrage (dompdf) : fragment sans <html>/<body>, styles inline (pas de Vite).
     Document interne (personnel uniquement, InfrastructurePolicy::downloadPdf). --}}
<style>
  * { font-family: DejaVu Sans, sans-serif; }
  body { color: #0B2233; font-size: 12px; }
  h1 { font-size: 20px; color: #0E4A72; margin: 0 0 4px; }
  h2 { font-size: 14px; color: #0E4A72; margin: 18px 0 6px; }
  .muted { color: #5B7487; }
  table { width: 100%; border-collapse: collapse; margin: 8px 0; }
  th { text-align: left; font-size: 10px; color: #5B7487; text-transform: uppercase; padding: 6px; border-bottom: 1px solid #DCE6EE; }
  td { padding: 6px; border-bottom: 1px solid #DCE6EE; vertical-align: top; }
  td.label { color: #5B7487; width: 34%; }
  .score { font-size: 26px; font-weight: bold; }
  .bar { height: 8px; background: #E8EEF3; border-radius: 4px; }
  .bar span { display: block; height: 8px; border-radius: 4px; }
  .footer { margin-top: 24px; font-size: 10px; }
</style>
@php
    $riskColors = ['faible' => '#2A9D8F', 'moyen' => '#1CA7EC', 'eleve' => '#F4A261', 'critique' => '#E63946'];
    $statusLabel = fn (?string $value) => \App\Enums\InfrastructureStatus::tryFrom((string) $value)?->label() ?? '—';
@endphp

<h1>AquaSecure · Fiche ouvrage {{ $infrastructure->code }}</h1>
<div class="muted">{{ $infrastructure->name }} · édité le {{ now()->format('d/m/Y à H:i') }}</div>

<h2>Informations</h2>
<table>
  <tr><td class="label">Type</td><td>{{ $infrastructure->type->label() }}</td></tr>
  <tr><td class="label">Zone</td><td>{{ $infrastructure->zone->name }} ({{ $infrastructure->zone->municipality }}, {{ $infrastructure->zone->governorate }})</td></tr>
  <tr><td class="label">Capacité</td><td>{{ $infrastructure->formattedCapacity() }}</td></tr>
  <tr><td class="label">Matériau</td><td>{{ $infrastructure->material ?? '—' }}</td></tr>
  <tr><td class="label">Mise en service</td><td>{{ $infrastructure->commissioned_year }} ({{ $infrastructure->age() }} ans)</td></tr>
  <tr><td class="label">Pression nominale</td><td>{{ $infrastructure->formattedPressure() ?? '—' }}</td></tr>
  <tr><td class="label">État</td><td>{{ $infrastructure->status->label() }}</td></tr>
  <tr><td class="label">Dernière inspection</td><td>{{ $infrastructure->last_inspection_at?->format('d/m/Y') ?? 'Non renseignée' }}</td></tr>
  <tr><td class="label">Prochaine inspection</td><td>{{ $infrastructure->next_inspection_at?->format('d/m/Y') ?? '—' }}@if ($infrastructure->isInspectionOverdue()) · en retard de {{ $infrastructure->inspectionDaysOverdue() }} jour(s)@endif</td></tr>
</table>

<h2>Score de risque</h2>
@if ($infrastructure->risk_score !== null)
<div><span class="score">{{ $infrastructure->risk_score }}</span> / 100 · risque {{ mb_strtolower($infrastructure->risk_level->label()) }}</div>
<div class="bar"><span style="width: {{ $infrastructure->risk_score }}%; background: {{ $riskColors[$infrastructure->risk_level->value] }}"></span></div>
<p>{{ $infrastructure->risk_summary }}</p>
<table>
  <tr><th>Facteur</th><th style="width: 18%">Points</th></tr>
  @foreach (collect($infrastructure->risk_factors ?? [])->sortByDesc('points') as $factor)
  <tr><td>{{ \Illuminate\Support\Str::ucfirst($factor['label']) }}</td><td>{{ number_format($factor['points'], 1, ',', ' ') }}</td></tr>
  @endforeach
</table>

<h2>Recommandations</h2>
<ol>
  @foreach ($infrastructure->risk_recommendations ?? [] as $recommendation)
  <li>{{ $recommendation }}</li>
  @endforeach
</ol>
<div class="muted">Score calculé le {{ $infrastructure->risk_computed_at?->format('d/m/Y à H:i') }} ; texte {{ $infrastructure->risk_source === 'ia' ? 'rédigé par l\'IA' : 'généré par des règles' }}.</div>
@else
<p class="muted">Ouvrage pas encore évalué.</p>
@endif

<h2>Dernières inspections</h2>
<table>
  <tr><th>Date</th><th>État</th><th>Note</th><th>Par</th></tr>
  @forelse ($inspections as $inspection)
  <tr>
    <td>{{ \Illuminate\Support\Carbon::parse($inspection->properties['date'])->format('d/m/Y') }}</td>
    <td>{{ $statusLabel($inspection->properties['old']['status'] ?? null) }} → {{ $statusLabel($inspection->properties['attributes']['status'] ?? null) }}</td>
    <td>{{ $inspection->properties['note'] ?: '—' }}</td>
    <td>{{ $inspection->causer?->name ?? 'Système' }}</td>
  </tr>
  @empty
  <tr><td colspan="4" class="muted">Aucune inspection enregistrée dans l'application.</td></tr>
  @endforelse
</table>

<h2>Derniers incidents</h2>
<table>
  <tr><th>Référence</th><th>Incident</th><th>Gravité</th><th>Statut</th><th>Signalé le</th></tr>
  @forelse ($infrastructure->incidents as $incident)
  <tr>
    <td>{{ $incident->reference }}</td>
    <td>{{ $incident->type->label() }} : {{ $incident->title }}</td>
    <td>{{ $incident->severity->label() }}</td>
    <td>{{ $incident->status->label() }}</td>
    <td>{{ $incident->created_at->format('d/m/Y') }}</td>
  </tr>
  @empty
  <tr><td colspan="5" class="muted">Aucun incident signalé sur cet ouvrage.</td></tr>
  @endforelse
</table>

<div class="footer muted">Document interne AquaSecure · score de risque calculé à partir de l'âge, de l'état, du matériau, des inspections, des incidents et des analyses d'eau.</div>
