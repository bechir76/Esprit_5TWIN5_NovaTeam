@extends('layouts.back.app')

@section('title', 'Financements')

@section('content')
<section data-page="financements" class="stack">
  <x-page-header title="Financements">
    <button class="btn btn-primary" type="button">+ Enregistrer un financement</button>
  </x-page-header>
  <div class="grid kpis">
    <x-kpi-card label="Total collecté 2026" value="518 900" unit="TND" />
    <x-kpi-card label="Contributions" value="1 947" />
    <x-kpi-card label="Part municipale" value="61 %" />
    <x-kpi-card label="Projets financés à 100 %" value="4" />
  </div>
  <div class="grid" style="grid-template-columns:repeat(auto-fit,minmax(min(100%,420px),1fr))">
    <div class="card"><div class="card-h"><h2>Contributions récentes</h2><select class="input" style="width:auto" aria-label="Projet"><option>Tous les projets</option></select></div><div class="table-wrap"><table style="min-width:560px"><thead><tr><th>Date</th><th>Source</th><th>Projet</th><th style="text-align:right">Montant</th></tr></thead><tbody>
      <tr><td class="mono">02/10/2026</td><td>Contribution citoyenne</td><td>Réservoir de La Soukra</td><td class="mono" style="text-align:right">50 TND</td></tr>
      <tr><td class="mono">01/10/2026</td><td>Association Eau Vive</td><td>Réservoir de La Soukra</td><td class="mono" style="text-align:right">15 000 TND</td></tr>
      <tr><td class="mono">29/09/2026</td><td>Municipalité de Mornag</td><td>Traitement nitrate Mornag</td><td class="mono" style="text-align:right">90 000 TND</td></tr>
      <tr><td class="mono">27/09/2026</td><td>Entreprise partenaire</td><td>Capteurs dans 12 écoles</td><td class="mono" style="text-align:right">12 000 TND</td></tr>
      <tr><td class="mono">25/09/2026</td><td>Contribution citoyenne</td><td>Conduites Bab Saadoun</td><td class="mono" style="text-align:right">100 TND</td></tr>
    </tbody></table></div></div>
    <div class="card"><div class="card-h"><h2>Progression par projet</h2></div><div class="card-b stack" style="gap:16px" id="fundProg"></div></div>
  </div>
</section>
@endsection
