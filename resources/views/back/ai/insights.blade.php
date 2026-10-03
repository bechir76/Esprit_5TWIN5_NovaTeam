@extends('layouts.back.app')

@section('title', 'Analyses IA')

@section('content')
<section data-page="ia" class="stack" style="gap:20px">
  <x-page-header title="Analyses IA" />
  <div class="ai-hero"><div><h2 style="color:#fff;font-size:1.25rem">Ce que l'IA a détecté cette semaine</h2><p>3 anomalies sur le réseau, 41 signalements classés automatiquement et 2 infrastructures passées en risque élevé.</p></div><span class="model">Dernière analyse : 03/10/2026 08:00</span></div>
  <div class="grid" style="grid-template-columns:repeat(auto-fit,minmax(min(100%,460px),1fr))">
    <div class="card"><div class="card-h"><h2>Score de risque prédictif</h2><span class="hint">Probabilité de défaillance à 90 jours</span></div><div class="table-wrap"><table style="min-width:480px"><thead><tr><th>Infrastructure</th><th>Facteurs</th><th style="width:38%">Risque</th></tr></thead><tbody id="riskTable"></tbody></table></div></div>
    <div class="card"><div class="card-h"><h2>Détection d'anomalies</h2><span class="badge b-crit dot">Pression · Ariana Nord</span></div><div class="card-b"><svg class="chart" id="anomChart" viewBox="0 0 520 220" width="100%" role="img" aria-label="Pression mesurée sur 24 heures avec bande attendue"></svg><div class="legend" style="margin-top:8px"><span><i style="background:var(--water)"></i>Pression mesurée (bar)</span><span><i style="background:var(--water-soft);border:1px solid var(--line)"></i>Plage attendue</span><span><i style="background:var(--crit)"></i>Anomalie</span></div></div></div>
  </div>
  <div class="grid" style="grid-template-columns:repeat(auto-fit,minmax(min(100%,460px),1fr))">
    <div class="card"><div class="card-h"><h2>Classification automatique des signalements</h2><span class="muted mono" style="font-size:.8rem">précision 92 %</span></div><div class="table-wrap"><table style="min-width:480px"><thead><tr><th>Extrait du signalement</th><th>Catégorie</th><th>Confiance</th></tr></thead><tbody>
      <tr><td>« de l'eau sort du trottoir depuis ce matin »</td><td><span class="badge b-info">Fuite</span></td><td class="mono">96 %</td></tr>
      <tr><td>« l'eau est marron et sent l'œuf pourri »</td><td><span class="badge b-crit">Contamination</span></td><td class="mono">93 %</td></tr>
      <tr><td>« plus d'eau depuis 2 jours au 3e étage »</td><td><span class="badge b-warn">Coupure</span></td><td class="mono">88 %</td></tr>
      <tr><td>« bruit de sifflement dans le compteur »</td><td><span class="badge b-info">Fuite</span></td><td class="mono">61 %</td></tr>
    </tbody></table></div></div>
    <div class="card"><div class="card-h"><h2>Priorisation recommandée des interventions</h2></div><div class="card-b">
      <div class="reco"><span class="n">1</span><div><strong>Forage de Mornag F3</strong><p class="muted">Nitrate au-dessus du seuil, 1 850 foyers exposés. Prélèvements et citerne mobile aujourd'hui.</p></div></div>
      <div class="reco"><span class="n">2</span><div><strong>Conduite DN 300 Ariana Nord</strong><p class="muted">Chute de pression nocturne typique d'une rupture. 5 signalements groupés à moins de 200 m.</p></div></div>
      <div class="reco"><span class="n">3</span><div><strong>Réservoir de La Soukra</strong><p class="muted">Risque de défaillance de 74 % à 90 jours. Avancer l'inspection du radier.</p></div></div>
    </div></div>
  </div>
</section>
@endsection
