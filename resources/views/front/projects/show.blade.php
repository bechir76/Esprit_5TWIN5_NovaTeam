@extends('layouts.front.app')

@section('title', 'Réhabilitation du réservoir de La Soukra')

@section('content')
<section class="screen" data-page="projet">
  <div class="wrap">
    <a href="{{ route('projects.index') }}" class="muted" style="font-size:.9rem;text-decoration:none">← Retour aux projets</a>
    <div class="screen-head" style="margin-top:14px"><div class="stack" style="gap:10px"><span class="badge b-warn">Décontamination</span><h2>Réhabilitation du réservoir de La Soukra</h2><p class="muted">Réservoir semi-enterré de 5 000 m³ construit en 1987. Fissures du radier et dépôts de manganèse détectés lors de l'inspection de mars 2026.</p></div></div>
    <div class="detail-layout">
      <div class="stack" style="gap:24px">
        <div class="proj-img card" style="background:linear-gradient(135deg,#0E5A85,#2EC4B6);border:0;min-height:260px"></div>
        <div class="card pad">
          <h3 style="margin-bottom:12px">Budget détaillé</h3>
          <div class="budget-row"><span>Vidange, nettoyage et désinfection</span><span class="mono">38 000 TND</span></div>
          <div class="budget-row"><span>Réparation du radier et étanchéité</span><span class="mono">112 000 TND</span></div>
          <div class="budget-row"><span>Remplacement des vannes d'isolement</span><span class="mono">46 000 TND</span></div>
          <div class="budget-row"><span>Capteurs de qualité connectés (chlore, turbidité)</span><span class="mono">24 000 TND</span></div>
          <div class="budget-row" style="border:0;font-weight:700"><span>Total</span><span class="mono">220 000 TND</span></div>
        </div>
        <div class="card pad">
          <h3 style="margin-bottom:16px">Avancement des travaux</h3>
          <div class="timeline">
            <div class="tl done"><strong>Inspection et diagnostic</strong><div class="muted" style="font-size:.88rem">12 mars 2026 · rapport publié</div></div>
            <div class="tl done"><strong>Validation du financement municipal</strong><div class="muted" style="font-size:.88rem">2 mai 2026 · 120 000 TND engagés</div></div>
            <div class="tl now"><strong>Collecte citoyenne en cours</strong><div class="muted" style="font-size:.88rem">Clôture le 30 novembre 2026</div></div>
            <div class="tl"><strong>Travaux de réhabilitation</strong><div class="muted" style="font-size:.88rem">Prévu janvier à avril 2027</div></div>
            <div class="tl" style="padding-bottom:0"><strong>Remise en service et contrôle qualité</strong><div class="muted" style="font-size:.88rem">Prévu mai 2027</div></div>
          </div>
        </div>
      </div>
      <aside class="stack" style="gap:20px;position:sticky;top:90px">
        <div class="card pad stack" style="gap:14px">
          <div><strong class="mono" style="font-size:1.8rem;font-family:var(--f-display)">168 400 TND</strong><div class="muted">collectés sur 220 000 TND</div></div>
          <div class="progress"><span style="width:77%"></span></div>
          <div class="row" style="justify-content:space-between;font-size:.88rem"><span><strong>77 %</strong> financé</span><span><strong>58</strong> jours restants</span></div>
          <div class="field"><label for="amount">Montant de votre contribution</label>
            <div class="seg"><input type="radio" name="amt" id="a1"><label for="a1">20 TND</label><input type="radio" name="amt" id="a2" checked><label for="a2">50 TND</label><input type="radio" name="amt" id="a3"><label for="a3">100 TND</label></div>
            <input class="input" id="amount" placeholder="Autre montant (TND)"></div>
          <button class="btn btn-primary" style="justify-content:center" type="button" data-toast="Merci, votre contribution de 50 TND est enregistrée.">Contribuer au projet</button>
          <p class="muted" style="font-size:.8rem">Démo : aucun paiement n'est effectué.</p>
        </div>
        <div class="card pad">
          <h3 style="margin-bottom:10px">Derniers financements</h3>
          <div class="budget-row"><span>Municipalité de La Soukra</span><span class="mono">120 000</span></div>
          <div class="budget-row"><span>Association Eau Vive</span><span class="mono">15 000</span></div>
          <div class="budget-row"><span>Contributions citoyennes (412)</span><span class="mono">33 400</span></div>
        </div>
      </aside>
    </div>
  </div>
</section>
@endsection
