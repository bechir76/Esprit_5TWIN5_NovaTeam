@extends('layouts.front.app')

@section('title', 'Signaler un incident')

@section('content')
<section class="screen" data-page="signaler">
  <div class="wrap">
    <div class="screen-head"><div class="stack" style="gap:8px"><span class="eyebrow">Nouveau signalement</span><h2>Signaler un incident</h2><p class="muted">Les champs marqués d'un astérisque sont obligatoires. Une urgence sanitaire ? Appelez aussi le numéro vert de votre régie.</p></div></div>
    <div class="report-layout">
      <form class="card" id="reportForm" method="POST" action="{{ route('incidents.store') }}" enctype="multipart/form-data" novalidate>
        @csrf
        <div class="form-sec">
          <div class="sec-title"><span>1/4</span><h3>Nature de l'incident</h3></div>
          <div class="field"><label>Type d'incident *</label>
            <div class="seg" role="radiogroup">
              <input type="radio" name="type" id="t1" value="fuite" @checked(old('type', 'fuite') === 'fuite')><label for="t1">💧 Fuite</label>
              <input type="radio" name="type" id="t2" value="contamination" @checked(old('type') === 'contamination')><label for="t2">⚠️ Contamination</label>
              <input type="radio" name="type" id="t3" value="coupure" @checked(old('type') === 'coupure')><label for="t3">🚱 Coupure</label>
            </div>
          </div>
          <x-form.select name="severity" label="Niveau de gravité *" value="moyen" :options="[
              'faible' => 'Faible : suintement, gêne mineure',
              'moyen' => 'Moyen : écoulement visible, pression réduite',
              'eleve' => 'Élevé : rue inondée, eau colorée ou odorante',
              'critique' => 'Critique : risque sanitaire ou quartier entier privé d\'eau',
          ]" />
          <x-form.input name="title" label="Titre *" placeholder="Ex. : Fuite importante devant l'école Ibn Khaldoun" />
          <x-form.textarea name="description" label="Description *" hint="Décrivez ce que vous voyez : couleur, odeur, débit, depuis quand." />
        </div>
        <div class="form-sec">
          <div class="sec-title"><span>2/4</span><h3>Localisation</h3></div>
          <div class="grid g2" style="gap:14px">
            <x-form.input name="address" label="Adresse *" placeholder="Avenue Habib Bourguiba, Ariana" />
            <x-form.select name="delegation" label="Délégation *" :options="['Ariana Ville', 'La Soukra', 'Raoued']" />
          </div>
          <div class="mini-map" aria-label="Position du signalement">
            <svg viewBox="0 0 600 160" width="100%" height="100%" preserveAspectRatio="xMidYMid slice"><rect width="600" height="160" fill="var(--surface-2)"/><path d="M0 60 L600 90 M0 130 L600 110 M180 0 L220 160 M420 0 L390 160" stroke="var(--line)" stroke-width="10"/><path d="M0 60 L600 90" stroke="var(--water)" stroke-width="2" stroke-dasharray="6 6" opacity=".6"/><circle cx="300" cy="75" r="22" fill="var(--water)" opacity=".2"/><circle cx="300" cy="75" r="8" fill="var(--water)" stroke="#fff" stroke-width="3"/></svg>
          </div>
          <button type="button" class="btn btn-ghost btn-sm" style="align-self:flex-start">📍 Utiliser ma position</button>
        </div>
        <div class="form-sec">
          <div class="sec-title"><span>3/4</span><h3>Photos</h3></div>
          <label class="drop" for="photos"><strong>Glissez vos photos ici</strong> ou cliquez pour parcourir<br><span style="font-size:.8rem">JPG ou PNG, 5 Mo maximum par photo, 4 photos au plus</span></label>
          <input type="file" id="photos" name="photos[]" accept="image/*" multiple hidden>
          <div class="thumbs"><div class="thumb"></div><div class="thumb" style="background:linear-gradient(135deg,#7AA6BE,#3B6E8B)"></div></div>
        </div>
        <div class="form-sec">
          <div class="sec-title"><span>4/4</span><h3>Vos coordonnées</h3></div>
          <div class="grid g2" style="gap:14px">
            <x-form.input name="name" label="Nom complet *" />
            <x-form.input name="phone" label="Téléphone" type="tel" />
          </div>
          <label class="check"><input type="checkbox" name="notify" value="1" @checked(old('notify', true))> Me notifier à chaque changement de statut</label>
          <div class="row" style="justify-content:flex-end"><button type="submit" name="action" value="draft" class="btn btn-ghost">Enregistrer le brouillon</button><button class="btn btn-primary" type="submit" name="action" value="send">Envoyer le signalement</button></div>
        </div>
      </form>
      <aside class="ai-panel" aria-live="polite">
        <div class="row" style="gap:10px"><span style="width:34px;height:34px;border-radius:9px;background:var(--water);display:grid;place-items:center">✦</span><div><strong>Assistant IA</strong><div style="font-size:.8rem;color:#9CBCD1">Analyse de la description et des photos</div></div></div>
        <div class="sugg"><div><div style="font-size:.78rem;color:#9CBCD1">Catégorie suggérée</div><strong>Fuite sur conduite</strong></div><span class="conf">94 %</span></div>
        <div class="sugg"><div><div style="font-size:.78rem;color:#9CBCD1">Gravité estimée</div><strong>Élevée</strong></div><span class="conf">81 %</span></div>
        <div class="sugg"><div><div style="font-size:.78rem;color:#9CBCD1">Ouvrage probable</div><strong>Conduite DN 300 · Ariana Nord</strong></div><span class="conf">76 %</span></div>
        <p style="font-size:.85rem;color:#C7DDEB;margin-top:14px">2 signalements similaires à moins de 200 m ces dernières 24 h. Le vôtre sera regroupé pour accélérer l'intervention.</p>
        <button class="btn btn-light btn-sm" type="button" style="margin-top:14px;width:100%;justify-content:center">Appliquer les suggestions</button>
      </aside>
    </div>
  </div>
</section>
@endsection
