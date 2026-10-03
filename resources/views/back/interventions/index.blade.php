@extends('layouts.back.app')

@section('title', 'Interventions')

@section('content')
<section data-page="interventions" class="stack">
  <x-page-header title="Interventions planifiées" crumb="Console / Interventions">
    <button class="btn btn-primary" type="button">+ Planifier</button>
  </x-page-header>
  <div class="card"><div class="table-wrap"><table><thead><tr><th>Date</th><th>Intervention</th><th>Incident lié</th><th>Équipe</th><th>Durée estimée</th><th>Statut</th></tr></thead><tbody>
    <tr><td class="mono">03/10 10:00</td><td><strong>Prélèvements qualité sur 3 points</strong></td><td class="mono">INC-2026-0915</td><td>Équipe qualité · Ben Arous</td><td class="mono">3 h</td><td><x-status-badge value="Planifiée" dot /></td></tr>
    <tr><td class="mono">03/10 14:30</td><td><strong>Réparation conduite DN 300</strong></td><td class="mono">INC-2026-0918</td><td>Équipe réseau · Ariana</td><td class="mono">6 h</td><td><x-status-badge value="En cours" variant="b-warn" dot /></td></tr>
    <tr><td class="mono">04/10 08:00</td><td><strong>Remplacement vanne d'isolement</strong></td><td class="mono">INC-2026-0907</td><td>Équipe réseau · Tunis</td><td class="mono">4 h</td><td><x-status-badge value="Planifiée" dot /></td></tr>
    <tr><td class="mono">02/10 09:15</td><td><strong>Remplacement compteur</strong></td><td class="mono">INC-2026-0899</td><td>Équipe d'astreinte</td><td class="mono">1 h</td><td><x-status-badge value="Terminée" dot /></td></tr>
  </tbody></table></div></div>
</section>
@endsection
