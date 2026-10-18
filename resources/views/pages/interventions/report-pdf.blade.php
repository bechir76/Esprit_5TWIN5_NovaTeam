{{-- Rapport d'intervention PDF (dompdf) : fragment sans <html>/<body>, styles inline (pas de Vite).
     Document interne (personnel uniquement, InterventionPolicy::downloadReport). --}}
@php($incident = $intervention->incident)
<style>
  * { font-family: DejaVu Sans, sans-serif; }
  body { color: #0B2233; font-size: 12px; }
  h1 { font-size: 20px; color: #0E4A72; margin: 0 0 4px; }
  h2 { font-size: 14px; color: #0E4A72; margin: 18px 0 6px; }
  .muted { color: #5B7487; }
  table { width: 100%; border-collapse: collapse; margin: 8px 0; }
  td { padding: 6px; border-bottom: 1px solid #DCE6EE; vertical-align: top; }
  td.label { color: #5B7487; width: 34%; }
  .report { white-space: pre-line; line-height: 1.5; border: 1px solid #DCE6EE; padding: 10px; }
  .footer { margin-top: 24px; font-size: 10px; }
</style>

<h1>AquaSecure · Rapport d'intervention</h1>
<div class="muted">Incident {{ $incident->reference }} · édité le {{ now()->format('d/m/Y à H:i') }}</div>

<h2>Incident</h2>
<table>
  <tr><td class="label">Titre</td><td>{{ $incident->title }}</td></tr>
  <tr><td class="label">Type · gravité</td><td>{{ $incident->type->label() }} · {{ $incident->severity->label() }}</td></tr>
  <tr><td class="label">Statut actuel</td><td>{{ $incident->status->label() }}</td></tr>
  <tr><td class="label">Localisation</td><td>{{ $incident->address }}</td></tr>
  <tr><td class="label">Ouvrage</td><td>{{ $incident->infrastructure ? $incident->infrastructure->code.' · '.$incident->infrastructure->name.' ('.$incident->infrastructure->zone->name.')' : 'Non rattaché' }}</td></tr>
  <tr><td class="label">Signalé le</td><td>{{ $incident->created_at->format('d/m/Y H:i') }}</td></tr>
</table>

<h2>Intervention</h2>
<table>
  <tr><td class="label">Intitulé</td><td>{{ $intervention->title }}</td></tr>
  <tr><td class="label">Statut</td><td>{{ $intervention->status->label() }}</td></tr>
  <tr><td class="label">Équipe · technicien</td><td>{{ $intervention->team }} · {{ $intervention->technician?->name ?? 'non assigné' }}</td></tr>
  <tr><td class="label">Prévue le</td><td>{{ $intervention->scheduled_at->format('d/m/Y H:i') }} (durée estimée {{ $intervention->formattedDuration() }})</td></tr>
  <tr><td class="label">Démarrée le</td><td>{{ $intervention->started_at?->format('d/m/Y H:i') ?? '—' }}</td></tr>
  <tr><td class="label">Terminée le</td><td>{{ $intervention->completed_at?->format('d/m/Y H:i') ?? '—' }}</td></tr>
  @if ($intervention->actualHours() !== null)
  <tr><td class="label">Durée réelle</td><td>{{ number_format($intervention->actualHours(), 1, ',', ' ') }} h</td></tr>
  @endif
</table>

<h2>Compte rendu</h2>
<div class="report">{{ $intervention->report ?: 'Aucun compte rendu.' }}</div>

<div class="footer muted">Document interne AquaSecure, généré automatiquement. Ne pas diffuser.</div>
